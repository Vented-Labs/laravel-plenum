<?php

declare(strict_types=1);

namespace Vented\Plenum\Exceptions;

use RuntimeException;

final class ConfigurationException extends RuntimeException
{
    public static function driverNotRegistered(string $driver): self
    {
        return new self("Plenum driver \"{$driver}\" is not registered.");
    }

    public static function enabledButEmpty(string $driver): self
    {
        return new self("Plenum driver \"{$driver}\" is enabled but no nodes are configured for it.");
    }
}
