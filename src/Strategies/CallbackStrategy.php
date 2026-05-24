<?php

declare(strict_types=1);

namespace Vented\Plenum\Strategies;

use Closure;
use Throwable;
use Vented\Plenum\Contracts\RoutingStrategy;

final class CallbackStrategy implements RoutingStrategy
{
    public function __construct(
        private readonly Closure $resolver,
        private readonly string $name = 'callback',
    ) {}

    public function resolve(): int|string|null
    {
        try {
            $key = ($this->resolver)();
        } catch (Throwable) {
            return null;
        }

        return match (true) {
            is_int($key) => $key,
            is_string($key) && $key !== '' => $key,
            default => null,
        };
    }

    public function name(): string
    {
        return $this->name;
    }
}
