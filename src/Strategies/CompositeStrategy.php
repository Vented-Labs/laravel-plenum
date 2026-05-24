<?php

declare(strict_types=1);

namespace Vented\Plenum\Strategies;

use Vented\Plenum\Contracts\RoutingStrategy;

final class CompositeStrategy implements RoutingStrategy
{
    /** @var array<int, RoutingStrategy> */
    private readonly array $strategies;

    public function __construct(RoutingStrategy ...$strategies)
    {
        $this->strategies = array_values($strategies);
    }

    public function resolve(): int|string|null
    {
        foreach ($this->strategies as $strategy) {
            $key = $strategy->resolve();
            if ($key !== null) {
                return $key;
            }
        }

        return null;
    }

    public function name(): string
    {
        $names = array_map(static fn (RoutingStrategy $s): string => $s->name(), $this->strategies);

        return 'composite['.implode(',', $names).']';
    }
}
