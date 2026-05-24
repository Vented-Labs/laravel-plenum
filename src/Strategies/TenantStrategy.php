<?php

declare(strict_types=1);

namespace Vented\Plenum\Strategies;

use Closure;
use Throwable;
use Vented\Plenum\Contracts\RoutingStrategy;

final class TenantStrategy implements RoutingStrategy
{
    public function __construct(private readonly Closure $resolver) {}

    public function resolve(): int|string|null
    {
        try {
            $tenant = ($this->resolver)();
        } catch (Throwable) {
            return null;
        }

        return match (true) {
            is_int($tenant) => $tenant,
            is_string($tenant) && $tenant !== '' => $tenant,
            default => null,
        };
    }

    public function name(): string
    {
        return 'tenant';
    }
}
