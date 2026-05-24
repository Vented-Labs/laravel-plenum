<?php

declare(strict_types=1);

namespace Vented\Plenum\Contracts;

interface HashRing
{
    /**
     * Replace the current set of nodes on the ring.
     *
     * @param  array<int, string>  $nodes
     */
    public function setNodes(array $nodes): void;

    /**
     * Look up the single node responsible for the given key.
     */
    public function lookup(int|string $key): string;

    /**
     * Look up the top-$count nodes for the given key, in preference order.
     *
     * @return array<int, string>
     */
    public function lookupList(int|string $key, int $count): array;

    /**
     * Get the current set of nodes on the ring.
     *
     * @return array<int, string>
     */
    public function nodes(): array;
}
