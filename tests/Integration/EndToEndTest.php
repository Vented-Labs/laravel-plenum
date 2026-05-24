<?php

declare(strict_types=1);

namespace Vented\Plenum\Tests\Integration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use PDO;
use Vented\Plenum\Middleware\RouteRequest;
use Vented\Plenum\Plenum;

beforeEach(function () {
    $this->requireBackends(['mysql_1', 'mysql_2', 'valkey_1', 'valkey_2']);

    $this->setConfig([
        'plenum.expose_debug_header' => true,
        'plenum.strategy' => 'plenum.test.callback-strategy',
        'plenum.drivers.database.nodes' => [
            'db_1' => ['host' => '127.0.0.1', 'port' => 33061],
            'db_2' => ['host' => '127.0.0.1', 'port' => 33062],
        ],
        'plenum.drivers.database.connection_template' => [
            'driver' => 'mysql',
            'database' => 'plenum_test',
            'username' => 'root',
            'password' => 'plenum',
            'charset' => 'utf8mb4',
            'prefix' => '',
            'options' => [PDO::ATTR_TIMEOUT => 2],
        ],
        'plenum.drivers.redis.nodes' => [
            'r_1' => ['host' => '127.0.0.1', 'port' => 63791],
            'r_2' => ['host' => '127.0.0.1', 'port' => 63792],
        ],
        'plenum.drivers.redis.connection_template' => [
            'database' => 0,
            'password' => null,
            'read_write_timeout' => 2,
        ],
        'database.redis.client' => 'phpredis',
    ]);

    // Default strategy callback returns a deterministic key; tests override per-test.
    $this->app->singleton('plenum.test.callback-strategy', fn () => new \Vented\Plenum\Strategies\CallbackStrategy(fn () => 'default', 'test-callback'));

    $this->rebuildPlenum();

    foreach (['db_1', 'db_2'] as $node) {
        DB::purge($node);
        DB::connection($node)->statement('DELETE FROM marker');
    }
    foreach (['r_1', 'r_2'] as $node) {
        Redis::connection($node)->command('flushdb');
    }
});

it('a request with a routing key activates a deterministic DB + Redis node and the write lands on it', function () {
    $key = 'tenant-7';

    // Register a callable strategy returning our key, then rebuild Plenum so it picks it up.
    $this->app->singleton('plenum.test.callback-strategy', fn () => new \Vented\Plenum\Strategies\CallbackStrategy(fn () => $key, 'test-callback'));
    $this->app->forgetInstance(\Vented\Plenum\Contracts\RoutingStrategy::class);
    /** @var Plenum $plenum */
    $plenum = $this->rebuildPlenum();

    $expectedDb = $plenum->nodeFor('database', $key);
    $expectedRedis = $plenum->nodeFor('redis', $key);
    $marker = 'e2e-'.uniqid('', true);

    Route::middleware(RouteRequest::class)->get('/_plenum_e2e', function () use ($marker) {
        DB::table('marker')->insert(['key_name' => $marker]);
        \Vented\Plenum\Facades\Plenum::redis()->command('set', [$marker, 'present']);

        return 'ok';
    });

    $response = $this->get('/_plenum_e2e');

    $response->assertOk();
    expect($response->headers->get('X-Plenum-database'))->toBe($expectedDb)
        ->and($response->headers->get('X-Plenum-redis'))->toBe($expectedRedis)
        ->and($response->headers->get('X-Plenum-Strategy'))->toBe('test-callback')
        ->and(DB::connection($expectedDb)->table('marker')->where('key_name', $marker)->exists())->toBeTrue()
        ->and(Redis::connection($expectedRedis)->command('get', [$marker]))->toBe('present');

    // And the *other* node has nothing.
    $otherDb = $expectedDb === 'db_1' ? 'db_2' : 'db_1';
    $otherRedis = $expectedRedis === 'r_1' ? 'r_2' : 'r_1';
    expect(DB::connection($otherDb)->table('marker')->where('key_name', $marker)->exists())->toBeFalse()
        ->and(Redis::connection($otherRedis)->command('get', [$marker]))->toBeIn([false, null]);
});

it('two different keys land on potentially-different nodes, but each key is deterministic', function () {
    Route::middleware(RouteRequest::class)->get('/_plenum_e2e/{tenant}', function (string $tenant) {
        return response('ok')->header('X-Plenum-Tenant', $tenant);
    });

    $captured = [];
    foreach (['tenant-1', 'tenant-1', 'tenant-2', 'tenant-1'] as $tenant) {
        $this->app->singleton('plenum.test.callback-strategy', fn () => new \Vented\Plenum\Strategies\CallbackStrategy(fn () => $tenant, 'test-callback'));
        $this->app->forgetInstance(\Vented\Plenum\Contracts\RoutingStrategy::class);
        $this->rebuildPlenum();

        $response = $this->get("/_plenum_e2e/{$tenant}");
        $captured[] = [$tenant, $response->headers->get('X-Plenum-database')];
    }

    // Every captured node is real (header was set by the middleware after the request ran).
    expect($captured[0][1])->toBeIn(['db_1', 'db_2'])
        ->and($captured[1][1])->toBeIn(['db_1', 'db_2'])
        ->and($captured[2][1])->toBeIn(['db_1', 'db_2'])
        ->and($captured[3][1])->toBeIn(['db_1', 'db_2'])
        ->and($captured[0][1])->toBe($captured[1][1])  // tenant-1 deterministic
        ->and($captured[0][1])->toBe($captured[3][1]); // tenant-1 again, same answer
    // tenant-2 may or may not land on the same node; we only assert determinism per key, not distinctness.
});
