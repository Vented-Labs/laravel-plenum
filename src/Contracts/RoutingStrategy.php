<?php

declare(strict_types=1);

namespace Vented\Plenum\Contracts;

interface RoutingStrategy
{
    /**
     * Resolve a routing key for the current request.
     *
     * Implementations MUST NOT throw — return null when no key can be determined.
     */
    public function resolve(): int|string|null;

    /**
     * Short identifier for debugging and the X-Plenum-Strategy header.
     */
    public function name(): string;
}
