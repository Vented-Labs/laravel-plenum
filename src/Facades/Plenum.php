<?php

declare(strict_types=1);

namespace Vented\Plenum\Facades;

use Closure;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Http\Request;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\HtmlString;
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
    /** @var (Closure(Request): bool)|null */
    public static ?Closure $authUsing = null;

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

    /**
     * Register a callback used to authorise dashboard requests. The callback
     * receives the inbound Request and must return true to allow access.
     *
     * @param  Closure(Request): bool  $callback
     */
    public static function auth(Closure $callback): void
    {
        static::$authUsing = $callback;
    }

    /**
     * Decide whether the given request may access the dashboard. Defaults to
     * "allowed in local, denied everywhere else" when no callback is set.
     */
    public static function check(Request $request): bool
    {
        $callback = static::$authUsing ?? static function (Request $request): bool {
            $app = static::getFacadeApplication();

            return $app !== null && $app->environment('local');
        };

        return $callback($request);
    }

    /**
     * Inline the bundled dashboard stylesheet so the Blade layout can embed
     * it without a separate HTTP request or vendor:publish step.
     */
    public static function css(): HtmlString
    {
        $path = __DIR__.'/../../dist/plenum.css';
        $css = is_file($path) ? (string) file_get_contents($path) : '';

        return new HtmlString('<style>'.$css.'</style>');
    }

    protected static function getFacadeAccessor(): string
    {
        return \Vented\Plenum\Plenum::class;
    }
}
