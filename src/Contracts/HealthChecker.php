<?php

declare(strict_types=1);

namespace Vented\Plenum\Contracts;

use Throwable;
use Vented\Plenum\Health\NodeStatus;

interface HealthChecker
{
    /**
     * Whether the named node is currently considered healthy. Implementations
     * should read from a shared cache rather than pinging on the hot path.
     */
    public function isHealthy(string $driver, string $node): bool;

    /**
     * Return the subset of $nodes considered healthy, in the original order.
     *
     * @param  array<int, string>  $nodes
     * @return array<int, string>
     */
    public function filterHealthy(string $driver, array $nodes): array;

    /**
     * Actively check the node via the provided driver and update the shared state.
     */
    public function probe(string $driver, string $node, ConnectionDriver $resolver): NodeStatus;

    /**
     * Mark the node as down. Dispatches NodeMarkedDown if the node was up.
     */
    public function markDown(string $driver, string $node, ?Throwable $reason = null): void;

    /**
     * Mark the node as up. Dispatches NodeRecovered if the node was down.
     */
    public function markUp(string $driver, string $node): void;
}
