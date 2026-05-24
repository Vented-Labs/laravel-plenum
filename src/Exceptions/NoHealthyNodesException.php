<?php

declare(strict_types=1);

namespace Vented\Plenum\Exceptions;

use RuntimeException;

final class NoHealthyNodesException extends RuntimeException
{
    public static function forDriver(string $driver): self
    {
        return new self("Plenum has no healthy nodes available for driver \"{$driver}\".");
    }

    public static function empty(string $driver): self
    {
        return new self("Plenum has no nodes registered for driver \"{$driver}\".");
    }
}
