<?php

declare(strict_types=1);

namespace Vented\Plenum;

use Illuminate\Contracts\Events\Dispatcher;
use Throwable;
use Vented\Plenum\Contracts\ConnectionDriver;
use Vented\Plenum\Contracts\HashRing;
use Vented\Plenum\Contracts\HealthChecker;
use Vented\Plenum\Contracts\RoutingStrategy;
use Vented\Plenum\Events\FailoverOccurred;
use Vented\Plenum\Exceptions\ConfigurationException;
use Vented\Plenum\Exceptions\NoHealthyNodesException;
use Vented\Plenum\Hashing\ConsistentHashRing;

final class Plenum
{
    /** @var array<string, ConnectionDriver> */
    private array $drivers = [];

    /** @var array<string, HashRing> */
    private array $rings = [];

    public function __construct(
        private readonly RoutingStrategy $strategy,
        private readonly HealthChecker $health,
        private readonly Dispatcher $events,
        private readonly int $hashReplicasPerNode = 64,
        private readonly int $maxFailoverAttempts = 3,
    ) {}

    public function registerDriver(ConnectionDriver $driver, ?HashRing $ring = null): self
    {
        $name = $driver->name();

        $this->drivers[$name] = $driver;
        $this->rings[$name] = $ring ?? new ConsistentHashRing(
            replicasPerNode: $this->hashReplicasPerNode,
            driverName: $name,
        );

        return $this;
    }

    /** @return array<string, ConnectionDriver> */
    public function drivers(): array
    {
        return $this->drivers;
    }

    public function driver(string $name): ConnectionDriver
    {
        return $this->drivers[$name] ?? throw ConfigurationException::driverNotRegistered($name);
    }

    public function strategy(): RoutingStrategy
    {
        return $this->strategy;
    }

    /**
     * Resolve the strategy key and activate the matching node on each driver.
     *
     * Every registered driver is deactivated first so the routing decision is
     * hermetic — under long-lived workers (Octane/FrankenPHP/RoadRunner) we
     * don't carry the previous request's pinned connection into this one.
     *
     * @return array<string, string>
     */
    public function routeCurrentRequest(): array
    {
        foreach ($this->drivers as $driver) {
            try {
                $driver->deactivate();
            } catch (Throwable) {
                // a failure in one driver must not affect routing on the others.
            }
        }

        $key = $this->strategy->resolve();
        if ($key === null) {
            return [];
        }

        $activated = [];

        foreach ($this->drivers as $name => $driver) {
            if ($driver->nodes() === []) {
                continue;
            }

            try {
                $node = $this->nodeFor($name, $key);
                $driver->activate($node);
                $activated[$name] = $node;
            } catch (Throwable) {
                // a failure in one driver must not affect routing on the others.
            }
        }

        return $activated;
    }

    public function nodeFor(string $driver, int|string $key): string
    {
        $instance = $this->driver($driver);
        $healthy = $this->health->filterHealthy($driver, $instance->nodes());

        if ($healthy === []) {
            throw NoHealthyNodesException::forDriver($driver);
        }

        $ring = $this->rings[$driver];
        $ring->setNodes($healthy);

        return $ring->lookup($key);
    }

    /**
     * @return array<int, string>
     */
    public function candidatesFor(string $driver, int|string $key, int $count = 2): array
    {
        $instance = $this->driver($driver);
        $healthy = $this->health->filterHealthy($driver, $instance->nodes());

        if ($healthy === []) {
            throw NoHealthyNodesException::forDriver($driver);
        }

        $ring = $this->rings[$driver];
        $ring->setNodes($healthy);

        return $ring->lookupList($key, $count);
    }

    /**
     * Execute the callback against the routed node, retrying on the next
     * healthy candidate when the driver's failover exceptions are thrown.
     *
     * The callback receives the activated node name and may ignore it.
     *
     * @template T
     *
     * @param callable(string): T $callback
     * @return T
     * @throws Throwable
     */
    public function execute(string $driver, int|string $key, callable $callback): mixed
    {
        $instance = $this->driver($driver);
        $candidates = $this->candidatesFor($driver, $key, $this->maxFailoverAttempts);
        $failoverExceptions = $instance->failoverExceptions();

        $previousNode = null;
        $lastException = null;

        foreach ($candidates as $node) {
            try {
                $instance->activate($node);
                $result = $callback($node);

                if ($previousNode !== null && $lastException !== null) {
                    $this->events->dispatch(new FailoverOccurred(
                        driver: $driver,
                        fromNode: $previousNode,
                        toNode: $node,
                        reason: $lastException,
                    ));
                }

                return $result;
            } catch (Throwable $e) {
                if (! self::matchesFailover($e, $failoverExceptions)) {
                    throw $e;
                }

                $this->health->markDown($driver, $node, $e);
                $previousNode = $node;
                $lastException = $e;
            }
        }

        throw $lastException ?? NoHealthyNodesException::forDriver($driver);
    }

    /**
     * @param  array<int, class-string<Throwable>|string>  $failoverExceptions
     */
    private static function matchesFailover(Throwable $e, array $failoverExceptions): bool
    {
        return array_any($failoverExceptions, fn ($class) => $e instanceof $class);
    }
}
