<?php

declare(strict_types=1);

namespace Vented\Plenum;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\DatabaseManager;
use Illuminate\Routing\Router;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Vented\Plenum\Console\DiagnoseCommand;
use Vented\Plenum\Console\DistributionCommand;
use Vented\Plenum\Console\ProbeCommand;
use Vented\Plenum\Contracts\ConnectionDriver;
use Vented\Plenum\Contracts\HealthChecker;
use Vented\Plenum\Contracts\RoutingStrategy;
use Vented\Plenum\Drivers\DatabaseDriver;
use Vented\Plenum\Drivers\RedisDriver;
use Vented\Plenum\Exceptions\ConfigurationException;
use Vented\Plenum\Health\PingHealthChecker;
use Vented\Plenum\Middleware\RouteRequest;
use Vented\Plenum\Strategies\AuthUserStrategy;
use Vented\Plenum\Strategies\SessionOnlyStrategy;

class PlenumServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-plenum')
            ->hasConfigFile()
            ->hasCommands([
                DiagnoseCommand::class,
                DistributionCommand::class,
                ProbeCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->registerHealthChecker();
        $this->registerStrategyAliases();
        $this->registerStrategy();
        $this->registerPlenum();
    }

    /**
     * @throws BindingResolutionException
     */
    public function packageBooted(): void
    {
        $this->maybeRegisterMiddleware();
    }

    private function registerHealthChecker(): void
    {
        $this->app->singleton(HealthChecker::class, function (Application $app): HealthChecker {
            $config = $app->make(ConfigRepository::class);
            $cacheStore = $config->get('plenum.health.cache_store');
            $cache = $app->make(CacheFactory::class)->store($cacheStore);

            return new PingHealthChecker(
                cache: $cache,
                events: $app->make(Dispatcher::class),
                cachePrefix: (string) $config->get('plenum.health.cache_prefix', 'plenum:health:'),
                healthyTtlSeconds: (int) $config->get('plenum.health.healthy_ttl_seconds', 10),
                downTtlSeconds: (int) $config->get('plenum.health.down_ttl_seconds', 30),
            );
        });
    }

    private function registerStrategyAliases(): void
    {
        $this->app->singleton(AuthUserStrategy::class, fn (Application $app) => new AuthUserStrategy($app->make(AuthFactory::class)));
        $this->app->alias(AuthUserStrategy::class, 'plenum.strategy.auth-user');

        $this->app->singleton(SessionOnlyStrategy::class, fn (Application $app) => new SessionOnlyStrategy($app->make(Session::class)));
        $this->app->alias(SessionOnlyStrategy::class, 'plenum.strategy.session');
    }

    private function registerStrategy(): void
    {
        $this->app->singleton(RoutingStrategy::class, function (Application $app): RoutingStrategy {
            $name = (string) $app->make(ConfigRepository::class)->get('plenum.strategy', 'auth-user');
            $alias = "plenum.strategy.{$name}";

            if ($app->bound($alias)) {
                return $app->make($alias);
            }

            return $app->make($name);
        });
    }

    private function registerPlenum(): void
    {
        $this->app->singleton(Plenum::class, function (Application $app): Plenum {
            $config = $app->make(ConfigRepository::class);

            $plenum = new Plenum(
                strategy: $app->make(RoutingStrategy::class),
                health: $app->make(HealthChecker::class),
                events: $app->make(Dispatcher::class),
                hashReplicasPerNode: (int) $config->get('plenum.hash.replicas_per_node', 64),
                maxFailoverAttempts: (int) $config->get('plenum.max_failover_attempts', 3),
            );

            foreach ($this->buildDrivers($app, $config) as $driver) {
                $plenum->registerDriver($driver);
            }

            return $plenum;
        });

        $this->app->alias(Plenum::class, 'plenum');
    }

    /**
     * @return array<int, ConnectionDriver>
     */
    private function buildDrivers(Container $app, ConfigRepository $config): array
    {
        $drivers = [];

        $databaseConfig = $config->get('plenum.drivers.database', []);
        if ($this->shouldRegister('database', $databaseConfig)) {
            $this->registerDatabaseConnections($config, $databaseConfig);
            $drivers[] = new DatabaseDriver(
                db: $app->make(DatabaseManager::class),
                config: $config,
                nodes: array_map('strval', array_keys($databaseConfig['nodes'])),
            );
        }

        $redisConfig = $config->get('plenum.drivers.redis', []);
        if ($this->shouldRegister('redis', $redisConfig)) {
            $this->registerRedisConnections($config, $redisConfig);
            $drivers[] = new RedisDriver(
                redis: $app->make(RedisFactory::class),
                container: $app,
                nodes: array_map('strval', array_keys($redisConfig['nodes'])),
            );
        }

        return $drivers;
    }

    /**
     * @param  array<string, mixed>  $driverConfig
     */
    private function shouldRegister(string $name, array $driverConfig): bool
    {
        $enabled = $driverConfig['enabled'] ?? null;
        $nodes = $driverConfig['nodes'] ?? [];

        if ($enabled === false) {
            return false;
        }

        if ($enabled === true && $nodes === []) {
            throw ConfigurationException::enabledButEmpty($name);
        }

        return $nodes !== [];
    }

    /**
     * @param  array<string, mixed>  $driverConfig
     */
    private function registerDatabaseConnections(ConfigRepository $config, array $driverConfig): void
    {
        $template = $driverConfig['connection_template'] ?? [];

        foreach ($driverConfig['nodes'] as $name => $hostPort) {
            $key = "database.connections.{$name}";
            if ($config->has($key)) {
                continue;
            }

            $config->set($key, array_merge($template, [
                'host' => $hostPort['host'],
                'port' => $hostPort['port'],
            ]));
        }
    }

    /**
     * @param  array<string, mixed>  $driverConfig
     */
    private function registerRedisConnections(ConfigRepository $config, array $driverConfig): void
    {
        $template = $driverConfig['connection_template'] ?? [];

        foreach ($driverConfig['nodes'] as $name => $hostPort) {
            $key = "database.redis.{$name}";
            if ($config->has($key)) {
                continue;
            }

            $config->set($key, array_merge($template, [
                'host' => $hostPort['host'],
                'port' => $hostPort['port'],
            ]));
        }

        $client = $driverConfig['client'] ?? null;
        // Treat null and missing as equivalent so apps can leave the entry out
        // entirely without us silently failing to install the client.
        if ($client !== null && $config->get('database.redis.client') === null) {
            $config->set('database.redis.client', $client);
        }
    }

    /**
     * @throws BindingResolutionException
     */
    private function maybeRegisterMiddleware(): void
    {
        $config = $this->app->make(ConfigRepository::class);

        if (! $config->get('plenum.middleware.auto_register', true)) {
            return;
        }

        if (! $this->app->bound(HttpKernel::class)) {
            return;
        }

        /** @var Router $router */
        $router = $this->app->make(Router::class);

        foreach ((array) $config->get('plenum.middleware.groups', ['web', 'api']) as $group) {
            $router->pushMiddlewareToGroup((string) $group, RouteRequest::class);
        }
    }
}
