<?php

declare(strict_types=1);

namespace Vented\Plenum\Facades;

use Illuminate\Contracts\Redis\Connection;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Support\Facades\Facade;
use RuntimeException;
use Vented\Plenum\Drivers\RedisDriver;

/**
 * @method static array<string, string> routeCurrentRequest()
 * @method static string nodeFor(string $driver, int|string $key)
 * @method static array<int, string> candidatesFor(string $driver, int|string $key, int $count = 2)
 * @method static mixed execute(string $driver, int|string $key, callable $callback)
 * @method static \Vented\Plenum\Contracts\RoutingStrategy strategy()
 * @method static \Vented\Plenum\Plenum setStrategy(\Vented\Plenum\Contracts\RoutingStrategy $strategy)
 * @method static \Vented\Plenum\Plenum registerDriver(\Vented\Plenum\Contracts\ConnectionDriver $driver, ?\Vented\Plenum\Contracts\HashRing $ring = null)
 * @method static array<string, \Vented\Plenum\Contracts\ConnectionDriver> drivers()
 * @method static \Vented\Plenum\Contracts\ConnectionDriver driver(string $name)
 *
 * @see \Vented\Plenum\Plenum
 */
class Plenum extends Facade
{
    /**
     * Resolve the Redis connection that the active routing decision points at.
     */
    public static function redis(): Connection
    {
        $app = static::getFacadeApplication();

        if ($app === null || ! $app->bound(RedisDriver::ACTIVE_BINDING)) {
            throw new RuntimeException(
                'Plenum has not routed a Redis connection for this request. '
                .'Ensure the RouteRequest middleware has run, or call routeCurrentRequest() manually first.'
            );
        }

        $node = (string) $app->make(RedisDriver::ACTIVE_BINDING);

        return $app->make(RedisFactory::class)->connection($node);
    }

    protected static function getFacadeAccessor(): string
    {
        return \Vented\Plenum\Plenum::class;
    }
}
