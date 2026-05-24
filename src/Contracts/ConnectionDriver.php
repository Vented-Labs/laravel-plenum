<?php

declare(strict_types=1);

namespace Vented\Plenum\Contracts;

use Throwable;

interface ConnectionDriver
{
    /**
     * Identifier for this driver, e.g. "database", "redis". Used as the key in
     * routed-connection maps, event payloads, and X-Plenum-{name} headers.
     */
    public function name(): string;

    /**
     * The set of connection names this driver routes between.
     *
     * @return array<int, string>
     */
    public function nodes(): array;

    /**
     * Make $node the active connection for the current request.
     *
     * Implementations should be cheap and side-effect-limited to the current
     * Laravel request lifecycle.
     */
    public function activate(string $node): void;

    /**
     * Cheap health check. Must return true if healthy, false otherwise.
     * Implementations MUST NOT throw — translate exceptions into a false return.
     */
    public function ping(string $node): bool;

    /**
     * Exception classes that indicate a connection failure on this driver.
     * The router's failover-aware execute() catches these and retries on the
     * next healthy candidate.
     *
     * @return array<int, class-string<Throwable>>
     */
    public function failoverExceptions(): array;
}
