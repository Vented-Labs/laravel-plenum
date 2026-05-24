<?php

declare(strict_types=1);

namespace Vented\Plenum\Testing;

use DateTimeImmutable;
use Throwable;
use Vented\Plenum\Contracts\ConnectionDriver;
use Vented\Plenum\Contracts\HealthChecker;
use Vented\Plenum\Health\NodeStatus;

final class FakeHealthChecker implements HealthChecker
{
    /** @var array<string, bool> Driver:node => true when DOWN. Missing keys are healthy. */
    private array $downSet = [];

    /**
     * @param  array<int, string>  $initiallyDown  Pre-marked entries, format "driver:node".
     */
    public function __construct(array $initiallyDown = [])
    {
        foreach ($initiallyDown as $key) {
            $this->downSet[$key] = true;
        }
    }

    public function isHealthy(string $driver, string $node): bool
    {
        return ! isset($this->downSet[$this->key($driver, $node)]);
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
            reason: $healthy ? null : 'fake ping returned false',
        );
    }

    public function markDown(string $driver, string $node, ?Throwable $reason = null): void
    {
        $this->downSet[$this->key($driver, $node)] = true;
    }

    public function markUp(string $driver, string $node): void
    {
        unset($this->downSet[$this->key($driver, $node)]);
    }

    private function key(string $driver, string $node): string
    {
        return "{$driver}:{$node}";
    }
}
