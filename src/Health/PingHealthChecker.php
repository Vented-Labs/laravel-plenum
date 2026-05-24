<?php

declare(strict_types=1);

namespace Vented\Plenum\Health;

use DateTimeImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Throwable;
use Vented\Plenum\Contracts\ConnectionDriver;
use Vented\Plenum\Contracts\HealthChecker;
use Vented\Plenum\Events\NodeMarkedDown;
use Vented\Plenum\Events\NodeRecovered;

final class PingHealthChecker implements HealthChecker
{
    private const STATUS_UP = 'up';
    private const STATUS_DOWN = 'down';

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly Dispatcher $events,
        private readonly string $cachePrefix = 'plenum:health:',
        private readonly int $healthyTtlSeconds = 10,
        private readonly int $downTtlSeconds = 30,
    ) {}

    public function isHealthy(string $driver, string $node): bool
    {
        return $this->cache->get($this->key($driver, $node)) !== self::STATUS_DOWN;
    }

    public function filterHealthy(string $driver, array $nodes): array
    {
        return array_values(array_filter(
            $nodes,
            fn (string $node): bool => $this->isHealthy($driver, $node),
        ));
    }

    public function probe(string $driver, string $node, ConnectionDriver $resolver): NodeStatus
    {
        $healthy = $resolver->ping($node);

        if ($healthy) {
            $this->markUp($driver, $node);
        } else {
            $this->markDown($driver, $node);
        }

        return new NodeStatus(
            driver: $driver,
            node: $node,
            healthy: $healthy,
            checkedAt: new DateTimeImmutable(),
            reason: $healthy ? null : 'ping returned false',
        );
    }

    public function markDown(string $driver, string $node, ?Throwable $reason = null): void
    {
        $transitioning = $this->isHealthy($driver, $node);

        $this->cache->put($this->key($driver, $node), self::STATUS_DOWN, $this->downTtlSeconds);

        if ($transitioning) {
            $this->events->dispatch(new NodeMarkedDown(
                driver: $driver,
                node: $node,
                reason: $reason?->getMessage(),
            ));
        }
    }

    public function markUp(string $driver, string $node): void
    {
        $transitioning = ! $this->isHealthy($driver, $node);

        $this->cache->put($this->key($driver, $node), self::STATUS_UP, $this->healthyTtlSeconds);

        if ($transitioning) {
            $this->events->dispatch(new NodeRecovered(driver: $driver, node: $node));
        }
    }

    private function key(string $driver, string $node): string
    {
        return "{$this->cachePrefix}{$driver}:{$node}";
    }
}
