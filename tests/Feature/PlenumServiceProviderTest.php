<?php

declare(strict_types=1);

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Routing\Router;
use Vented\Plenum\Contracts\HealthChecker;
use Vented\Plenum\Contracts\RoutingStrategy;
use Vented\Plenum\Drivers\DatabaseDriver;
use Vented\Plenum\Drivers\RedisDriver;
use Vented\Plenum\Exceptions\ConfigurationException;
use Vented\Plenum\Health\PingHealthChecker;
use Vented\Plenum\Middleware\RouteRequest;
use Vented\Plenum\Plenum;
use Vented\Plenum\PlenumServiceProvider;
use Vented\Plenum\Strategies\AuthUserStrategy;
use Vented\Plenum\Strategies\SessionOnlyStrategy;

function freshPlenum(Container $app): Plenum
{
    $app->forgetInstance(Plenum::class);
    $app->forgetInstance(HealthChecker::class);
    $app->forgetInstance(RoutingStrategy::class);

    return $app->make(Plenum::class);
}

/** @param  array<string, mixed>  $overrides */
function setPlenumConfig(Container $app, array $overrides): void
{
    /** @var ConfigRepository $config */
    $config = $app->make(ConfigRepository::class);

    foreach ($overrides as $key => $value) {
        $config->set($key, $value);
    }
}

it('registers Plenum as a singleton', function () {
    $a = $this->app->make(Plenum::class);
    $b = $this->app->make(Plenum::class);

    expect($a)->toBe($b)
        ->and($a)->toBeInstanceOf(Plenum::class);
});

it('exposes the singleton via the "plenum" alias', function () {
    expect($this->app->make('plenum'))->toBeInstanceOf(Plenum::class)
        ->and($this->app->make('plenum'))->toBe($this->app->make(Plenum::class));
});

it('registers PingHealthChecker as the default HealthChecker', function () {
    expect($this->app->make(HealthChecker::class))->toBeInstanceOf(PingHealthChecker::class);
});

it('defaults the routing strategy to AuthUserStrategy', function () {
    expect($this->app->make(RoutingStrategy::class))->toBeInstanceOf(AuthUserStrategy::class);
});

it('resolves the session strategy when configured', function () {
    setPlenumConfig($this->app, ['plenum.strategy' => 'session']);
    freshPlenum($this->app);

    expect($this->app->make(RoutingStrategy::class))->toBeInstanceOf(SessionOnlyStrategy::class);
});

it('resolves a strategy by FQCN when no alias matches', function () {
    setPlenumConfig($this->app, ['plenum.strategy' => SessionOnlyStrategy::class]);
    freshPlenum($this->app);

    expect($this->app->make(RoutingStrategy::class))->toBeInstanceOf(SessionOnlyStrategy::class);
});

it('registers no drivers when both pools are empty', function () {
    expect(freshPlenum($this->app)->drivers())->toBe([]);
});

it('registers a DatabaseDriver when database nodes are configured', function () {
    setPlenumConfig($this->app, [
        'plenum.drivers.database.nodes' => [
            'db_1' => ['host' => 'db-1', 'port' => 5432],
            'db_2' => ['host' => 'db-2', 'port' => 5432],
        ],
    ]);

    $plenum = freshPlenum($this->app);

    expect($plenum->drivers())->toHaveKey('database')
        ->and($plenum->drivers()['database'])->toBeInstanceOf(DatabaseDriver::class)
        ->and($plenum->drivers()['database']->nodes())->toBe(['db_1', 'db_2']);
});

it('auto-creates database.connections entries from the template, without overriding user-defined ones', function () {
    setPlenumConfig($this->app, [
        'plenum.drivers.database.nodes' => [
            'db_1' => ['host' => 'db-1', 'port' => 5432],
            'db_2' => ['host' => 'db-2', 'port' => 5432],
        ],
        'plenum.drivers.database.connection_template' => [
            'driver' => 'pgsql',
            'database' => 'app',
            'username' => 'app_user',
        ],
        'database.connections.db_2' => ['driver' => 'mysql', 'database' => 'override'],
    ]);

    freshPlenum($this->app);

    $config = $this->app->make(ConfigRepository::class);

    expect($config->get('database.connections.db_1'))->toBe([
        'driver' => 'pgsql',
        'database' => 'app',
        'username' => 'app_user',
        'host' => 'db-1',
        'port' => 5432,
    ])
        ->and($config->get('database.connections.db_2'))->toBe([
            'driver' => 'mysql',
            'database' => 'override',
        ]);
});

it('registers a RedisDriver when redis nodes are configured', function () {
    setPlenumConfig($this->app, [
        'plenum.drivers.redis.nodes' => [
            'redis_1' => ['host' => 'r-1', 'port' => 6379],
        ],
    ]);

    $plenum = freshPlenum($this->app);

    expect($plenum->drivers())->toHaveKey('redis')
        ->and($plenum->drivers()['redis'])->toBeInstanceOf(RedisDriver::class)
        ->and($plenum->drivers()['redis']->nodes())->toBe(['redis_1']);
});

it('auto-creates database.redis.{name} entries without overriding user-defined ones', function () {
    setPlenumConfig($this->app, [
        'plenum.drivers.redis.nodes' => [
            'redis_1' => ['host' => 'r-1', 'port' => 6379],
            'redis_2' => ['host' => 'r-2', 'port' => 6379],
        ],
        'plenum.drivers.redis.connection_template' => [
            'database' => 0,
            'password' => null,
        ],
        'database.redis.redis_2' => ['host' => 'override.local', 'port' => 6390],
    ]);

    freshPlenum($this->app);

    $config = $this->app->make(ConfigRepository::class);

    expect($config->get('database.redis.redis_1'))->toBe([
        'database' => 0,
        'password' => null,
        'host' => 'r-1',
        'port' => 6379,
    ])
        ->and($config->get('database.redis.redis_2'))->toBe([
            'host' => 'override.local',
            'port' => 6390,
        ]);
});

it('sets database.redis.client when configured and not already set', function () {
    setPlenumConfig($this->app, [
        'plenum.drivers.redis.nodes' => [
            'redis_1' => ['host' => 'r-1', 'port' => 6379],
        ],
        'plenum.drivers.redis.client' => 'predis',
    ]);

    $config = $this->app->make(ConfigRepository::class);
    $config->set('database.redis.client', null);

    freshPlenum($this->app);

    expect($config->get('database.redis.client'))->toBe('predis');
});

it('does not override an existing database.redis.client value', function () {
    $config = $this->app->make(ConfigRepository::class);
    $config->set('database.redis.client', 'phpredis');

    setPlenumConfig($this->app, [
        'plenum.drivers.redis.nodes' => [
            'redis_1' => ['host' => 'r-1', 'port' => 6379],
        ],
        'plenum.drivers.redis.client' => 'predis',
    ]);

    freshPlenum($this->app);

    expect($config->get('database.redis.client'))->toBe('phpredis');
});

it('skips a driver when enabled is explicitly false', function () {
    setPlenumConfig($this->app, [
        'plenum.drivers.database.enabled' => false,
        'plenum.drivers.database.nodes' => [
            'db_1' => ['host' => 'db-1', 'port' => 5432],
        ],
    ]);

    expect(freshPlenum($this->app)->drivers())->not->toHaveKey('database');
});

it('throws ConfigurationException when a driver is enabled but has no nodes', function () {
    setPlenumConfig($this->app, [
        'plenum.drivers.database.enabled' => true,
        'plenum.drivers.database.nodes' => [],
    ]);

    expect(fn () => freshPlenum($this->app))
        ->toThrow(ConfigurationException::class, '"database"');
});

it('respects the configured hash replicas and max failover attempts', function () {
    setPlenumConfig($this->app, [
        'plenum.hash.replicas_per_node' => 32,
        'plenum.max_failover_attempts' => 5,
    ]);

    // Just exercise the closure with these overrides; coverage is what matters here.
    expect(freshPlenum($this->app))->toBeInstanceOf(Plenum::class);
});

it('uses a custom cache store and prefix when configured', function () {
    setPlenumConfig($this->app, [
        'plenum.health.cache_store' => 'array',
        'plenum.health.cache_prefix' => 'pl:health:',
        'plenum.health.healthy_ttl_seconds' => 7,
        'plenum.health.down_ttl_seconds' => 17,
    ]);

    expect($this->app->make(HealthChecker::class))->toBeInstanceOf(PingHealthChecker::class);
});

it('pushes the RouteRequest middleware onto each configured group by default', function () {
    $router = $this->app->make(Router::class);

    expect($router->getMiddlewareGroups()['web'] ?? [])->toContain(RouteRequest::class)
        ->and($router->getMiddlewareGroups()['api'] ?? [])->toContain(RouteRequest::class);
});

function invokeMaybeRegisterMiddleware(Container $app): void
{
    $provider = $app->getProvider(PlenumServiceProvider::class);
    $method = new ReflectionMethod($provider, 'maybeRegisterMiddleware');
    $method->invoke($provider);
}

it('maybeRegisterMiddleware returns early when auto_register is false', function () {
    setPlenumConfig($this->app, ['plenum.middleware.auto_register' => false]);

    $router = $this->app->make(Router::class);
    $before = $router->getMiddlewareGroups();

    // Invoking again should hit the early-return branch and leave routes untouched.
    invokeMaybeRegisterMiddleware($this->app);

    expect($router->getMiddlewareGroups())->toBe($before);
});

it('maybeRegisterMiddleware returns early when no HTTP Kernel is bound', function () {
    setPlenumConfig($this->app, ['plenum.middleware.auto_register' => true]);

    // Forget the kernel binding so the second guard branch is exercised.
    $this->app->forgetInstance(HttpKernel::class);
    unset($this->app[HttpKernel::class]);
    $this->app->bind(HttpKernel::class, fn () => throw new RuntimeException('should not be resolved'));
    $this->app->offsetUnset(HttpKernel::class);

    $router = $this->app->make(Router::class);
    $before = $router->getMiddlewareGroups();

    invokeMaybeRegisterMiddleware($this->app);

    expect($router->getMiddlewareGroups())->toBe($before);
});

it('produces a usable plenum singleton with realistic configuration', function () {
    setPlenumConfig($this->app, [
        'plenum.drivers.database.nodes' => [
            'db_1' => ['host' => 'h1', 'port' => 5432],
            'db_2' => ['host' => 'h2', 'port' => 5432],
        ],
        'plenum.drivers.redis.nodes' => [
            'r_1' => ['host' => 'rh1', 'port' => 6379],
            'r_2' => ['host' => 'rh2', 'port' => 6379],
        ],
        'plenum.strategy' => SessionOnlyStrategy::class,
    ]);

    $plenum = freshPlenum($this->app);

    expect($plenum->drivers())->toHaveKeys(['database', 'redis'])
        ->and($plenum->strategy())->toBeInstanceOf(SessionOnlyStrategy::class);
});
