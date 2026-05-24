# Laravel Plenum

[![Latest Version on Packagist](https://img.shields.io/packagist/v/vented/laravel-plenum.svg?style=flat-square)](https://packagist.org/packages/vented/laravel-plenum)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/Vented-Labs/laravel-plenum/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/Vented-Labs/laravel-plenum/actions?query=workflow%3Atests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/Vented-Labs/laravel-plenum/php-cs-fixer.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/Vented-Labs/laravel-plenum/actions?query=workflow%3A"php-cs-fixer"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/vented/laravel-plenum.svg?style=flat-square)](https://packagist.org/packages/vented/laravel-plenum)

Application-layer routing for Laravel. Pin requests to specific backing connections — databases, Redis nodes, or any custom resource Laravel can address by connection name — using consistent hashing, with pluggable routing-key strategies, active health monitoring, and automatic failover.

<p align="center">
    <img src="art/dashboard.png" alt="Plenum dashboard showing per-driver node health and key distribution, with light mode on the left and dark mode on the right." width="100%">
</p>

> [!WARNING]
> **Experimental — not for production use.** Laravel Plenum is at `0.1.0` and the API, configuration keys, and runtime behaviour may change without notice between point releases. Health-check semantics, event payloads, and the hashing implementation are not yet stable. Please evaluate in development or staging environments only until a `1.0` release is tagged.

## What and why

When you run a pool of interchangeable backends — a multi-master Postgres cluster, a Redis deployment, an application-sharded service — you usually want a stable mapping from some logical identity (user, tenant, project, session) to a specific backend, so replication can catch up or cache locality holds.

Load-balancer sticky sessions don't solve this: the same user on two devices needs the same backend, and your load balancer doesn't know what application identity means. Plenum handles it at the application layer. On each request it computes a routing key, consistent-hashes it across the healthy backends, and sets the appropriate Laravel default connection. When a node fails its health check, traffic reshuffles around it automatically.

## Requirements

- PHP 8.4+
- Laravel 13+
- A cache store (used to share health state across workers)

## Installation

```bash
composer require vented/laravel-plenum
```

Publish the config:

```bash
php artisan vendor:publish --tag="plenum-config"
```

The package auto-registers its service provider and facade. No migrations or views to publish — Plenum doesn't persist anything to your database.

## Quick start: database routing

Add your node pool to `.env`:

```dotenv
PLENUM_DB_NODES="db_1=db-1.internal:5432,db_2=db-2.internal:5432,db_3=db-3.internal:5432"
PLENUM_DB_DRIVER=pgsql
PLENUM_DB_DATABASE=appdb
PLENUM_DB_USERNAME=app
PLENUM_DB_PASSWORD=secret
PLENUM_DB_SSLMODE=require

PLENUM_STRATEGY=auth-user
```

That's it. Plenum will register each node as a Laravel database connection (`db_1`, `db_2`, `db_3`) on boot, then on every request the middleware computes a routing key from the authenticated user, hashes it across the healthy nodes, and calls `DB::setDefaultConnection()` for that request. All Eloquent queries, raw queries, and `DB::` calls in that request go to the chosen node.

## Quick start: Redis routing

```dotenv
PLENUM_REDIS_NODES="redis_1=redis-1.internal:6379,redis_2=redis-2.internal:6379"
PLENUM_REDIS_PASSWORD=
PLENUM_REDIS_DATABASE=0
PLENUM_REDIS_CLIENT=phpredis
```

Resolve the routed Redis connection at the call site:

```php
Plenum::redis()->set('cache:key', 'value');
```

## Routing strategies

A strategy answers one question: *given the current request, what's the routing key?* Plenum ships five built-ins:

- `auth-user` — `auth()->id()` (returns `null` for guests; wrap with `composite` if you need a session fallback)
- `session-only` — session ID always
- `tenant` — wraps a closure that returns your tenant identifier, useful with `stancl/tenancy` or similar
- `callback` — a closure registered at boot time, with a custom name for the `X-Plenum-Strategy` header
- `composite` — tries strategies in order until one returns a non-null key

For example, to route by authenticated user and fall back to the session for guests, bind a composite:

```php
use Vented\Plenum\Contracts\RoutingStrategy;
use Vented\Plenum\Strategies\AuthUserStrategy;
use Vented\Plenum\Strategies\CompositeStrategy;
use Vented\Plenum\Strategies\SessionOnlyStrategy;

$this->app->singleton(RoutingStrategy::class, fn ($app) => new CompositeStrategy(
    $app->make(AuthUserStrategy::class),
    $app->make(SessionOnlyStrategy::class),
));
```

Set the active strategy via `PLENUM_STRATEGY` or by binding your own:

```php
use Vented\Plenum\Contracts\RoutingStrategy;

final class ProjectStrategy implements RoutingStrategy
{
    public function resolve(): int|string|null
    {
        return request()->route('project')?->id;
    }

    public function name(): string
    {
        return 'project';
    }
}

// In a service provider:
$this->app->bind(RoutingStrategy::class, ProjectStrategy::class);
```

Strategies must never throw — return `null` if no key can be determined and the router will handle it.

## Multiple drivers in one app

Register both pools and the same routing key drives both:

```dotenv
PLENUM_DB_NODES="db_1=...,db_2=...,db_3=..."
PLENUM_REDIS_NODES="redis_1=...,redis_2=...,redis_3=..."
PLENUM_STRATEGY=tenant
```

Tenant 42 will land on `db_2` *and* `redis_2` (or whichever pair the hash ring assigns) — every request, every worker, deterministically. Drivers are independent: a Redis failure in one request won't affect database routing in that same request.

## Health checks and failover

The default `PingHealthChecker` delegates to each driver: a `SELECT 1` for database nodes, `PING` for Redis. Results are cached briefly (10s for healthy, 30s for down by default) so you're not pinging on every request.

Plenum ships a `plenum:probe` command but does not schedule it for you. Wire it into Laravel's scheduler so the cached state stays fresh — e.g. in `routes/console.php` (Laravel 11+):

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('plenum:probe')->everyTenSeconds();
```

Or, in long-lived environments like supervisord, run `php artisan plenum:probe --watch` as a daemon.

When a node fails, Plenum dispatches `NodeMarkedDown` and the ring rehashes around the survivors. When it comes back, `NodeRecovered` fires. The `Plenum::execute()` helper wraps an operation with automatic retry-on-different-node behaviour and dispatches `FailoverOccurred` when it kicks in.

Tune via env:

```dotenv
PLENUM_PROBE_INTERVAL=10
PLENUM_HEALTHY_CACHE_TTL=10
PLENUM_DOWN_CACHE_TTL=30
PLENUM_PROBE_TIMEOUT=3
```

### Custom health checker (pgEdge example)

For pgEdge users who want to factor Spock replication lag into the health decision:

```php
use Vented\Plenum\Contracts\HealthChecker;

final class SpockLagAwareHealthChecker implements HealthChecker
{
    public function probe(string $driver, string $node, ConnectionDriver $resolver): NodeStatus
    {
        // Run SELECT lag_bytes FROM spock.lag_tracker and mark down if above threshold.
    }
    // ... other methods
}
```

Bind it in a service provider and Plenum will use it instead of the default.

## Background jobs and queue workers

Queue jobs don't carry an HTTP request, so the auth-user or session strategies have nothing to resolve. Pass the routing key into the job's constructor and route from inside `handle()`:

```php
final class ProcessProjectReport implements ShouldQueue
{
    public function __construct(public readonly int $projectId) {}

    public function handle(): void
    {
        Plenum::execute('database', $this->projectId, function () {
            // All Eloquent / DB:: calls in here go to the routed node,
            // and a failover candidate is tried automatically if it fails.
        });
    }
}
```

`Plenum::execute()` is the recommended entry point for any work that should be retried on the next healthy node on a connection-level failure.

## Debugging

Set `PLENUM_EXPOSE_DEBUG_HEADER=true` and every response carries `X-Plenum-database`, `X-Plenum-redis`, and `X-Plenum-Strategy` headers showing which node served the request and why. Three Artisan commands help operationally:

```bash
php artisan plenum:diagnose          # show config, current health state, ring layout
php artisan plenum:distribution      # simulate distribution across N synthetic keys
php artisan plenum:probe --watch     # long-lived prober for supervisord
```

Listen to the events to wire up alerting:

```php
Event::listen(NodeMarkedDown::class, fn ($e) => Slack::alert(
    "{$e->driver} node {$e->node} marked down: {$e->reason}"
));
```

### Dashboard

Plenum ships a small read-only status page that visualises the same information as `plenum:diagnose` and `plenum:distribution` in a browser. It's mounted at `/plenum` and is **enabled by default in `local`** only — production and staging serve a 404 unless you opt in.

To expose it outside local, set the env var and register an auth gate:

```dotenv
PLENUM_DASHBOARD_ENABLED=true
```

```php
// In a service provider's boot() method
use Vented\Plenum\Facades\Plenum;

Plenum::auth(fn ($request) => $request->user()?->can('viewPlenum') ?? false);
```

Without a custom gate, non-local requests return 403. The callback receives the inbound `Request` and must return `true` to allow access.

Configurable via env:

```dotenv
PLENUM_DASHBOARD_PATH=admin/plenum        # default: plenum
PLENUM_DASHBOARD_DOMAIN=admin.example.com # optional
PLENUM_DASHBOARD_SAMPLES=1000             # distribution sample count
```

The page ships its own bundled stylesheet inline — no asset publishing required. If you want to theme it, publish the view and edit it directly:

```bash
php artisan vendor:publish --tag="plenum-views"
```

## Operational notes

Adding or removing a node reshuffles roughly `1/N` of the keys — consistent hashing's main selling point. Plan capacity for the brief surge of cache misses or replication catch-up during a node change.

If every node is unhealthy, Plenum throws `NoHealthyNodesException` rather than guessing — fail loudly, fail visibly, page someone.

## Testing

Plenum ships test helpers:

```php
use Vented\Plenum\Testing\FakeStrategy;
use Vented\Plenum\Testing\FakeHealthChecker;

$this->app->bind(RoutingStrategy::class, fn () => new FakeStrategy('test-key-42'));
$this->app->bind(HealthChecker::class, FakeHealthChecker::class);
```

Then assert against `Plenum::nodeFor()` deterministically.

Run the package's own test suite:

```bash
composer test
```

## FAQ

**Does this replace HAProxy / Traefik / a load balancer?** No. Your load balancer still distributes requests across web servers. Plenum routes *application data access* once the request is inside a Laravel worker.

**Does this rewrite SQL or split reads from writes?** No. v1.0 routes all reads and writes for a given key to the same node. Read/write splitting is a possible future addition.

**Does it work with a single-node pool?** Yes — degenerate case, but supported.

**What happens if I install it but don't configure any nodes?** It becomes a no-op. Installed-but-unconfigured won't crash your app.

## Comparison to alternatives

| Approach | Per-user pinning | Cross-device | Survives node changes | App-aware |
|---|---|---|---|---|
| HAProxy IP-hash | ✗ (enterprise NAT) | ✗ | partially | ✗ |
| Sticky cookies | ✗ (per-device) | ✗ | ✗ | ✗ |
| HAProxy header hashing | partially | depends | partially | partially |
| Dedicated proxies (pgBouncer, etc.) | depends | depends | yes | ✗ |
| **Laravel Plenum** | ✓ | ✓ | ✓ (consistent hash) | ✓ |

## Changelog

See [CHANGELOG](CHANGELOG.md).

## Contributing

See [CONTRIBUTING](CONTRIBUTING.md).

## Security Vulnerabilities

See [the security policy](../../security/policy).

## Credits

- [Mason Curry](https://github.com/masoncurry)
- [All Contributors](../../contributors)

## License

MIT. See [LICENSE](LICENSE.md).
