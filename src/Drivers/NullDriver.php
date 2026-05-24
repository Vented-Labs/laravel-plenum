<?php

declare(strict_types=1);

namespace Vented\Plenum\Drivers;

use Vented\Plenum\Contracts\ConnectionDriver;

final class NullDriver implements ConnectionDriver
{
    /** @var array<int, string> */
    private readonly array $nodes;

    /**
     * @param  array<int, string>  $nodes
     */
    public function __construct(
        private readonly string $name = 'null',
        array $nodes = [],
    ) {
        $this->nodes = array_values($nodes);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function nodes(): array
    {
        return $this->nodes;
    }

    public function activate(string $node): void
    {
        // intentionally empty — used for graceful partial-rollout
    }

    public function ping(string $node): bool
    {
        return true;
    }

    public function failoverExceptions(): array
    {
        return [];
    }
}
