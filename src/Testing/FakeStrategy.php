<?php

declare(strict_types=1);

namespace Vented\Plenum\Testing;

use Vented\Plenum\Contracts\RoutingStrategy;

final class FakeStrategy implements RoutingStrategy
{
    public function __construct(
        private int|string|null $key = null,
        private readonly string $name = 'fake',
    ) {}

    public function resolve(): int|string|null
    {
        return $this->key;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function setKey(int|string|null $key): void
    {
        $this->key = $key;
    }
}
