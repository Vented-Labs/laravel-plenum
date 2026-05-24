<?php

declare(strict_types=1);

namespace Vented\Plenum\Tests\Integration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PDO;
use Vented\Plenum\Events\FailoverOccurred;
use Vented\Plenum\Plenum;

afterEach(function () {
    // Reset event listener buckets between tests.
    Event::forget(FailoverOccurred::class);
});

beforeEach(function () {
    $this->requireBackends(['mysql_1', 'mysql_2']);

    $this->setConfig([
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
            'options' => [
                PDO::ATTR_TIMEOUT => 2,
            ],
        ],
    ]);

    $this->rebuildPlenum();

    foreach (['db_1', 'db_2'] as $node) {
        DB::purge($node);
        DB::connection($node)->statement('DELETE FROM marker');
    }
});

it('activate() pins DB::connection() and the routed connection answers SELECT 1', function () {
    /** @var Plenum $plenum */
    $plenum = $this->app->make(Plenum::class);

    $plenum->driver('database')->activate('db_2');

    expect(DB::connection()->getName())->toBe('db_2')
        ->and(DB::select('select 1 as ok'))->toEqual([(object) ['ok' => 1]]);
});

it('nodeFor() is deterministic for a given key', function () {
    /** @var Plenum $plenum */
    $plenum = $this->app->make(Plenum::class);

    expect($plenum->nodeFor('database', 'tenant-42'))
        ->toBe($plenum->nodeFor('database', 'tenant-42'))
        ->toBe($plenum->nodeFor('database', 'tenant-42'));
});

it('ping() returns true for live nodes and false for an unreachable host', function () {
    /** @var Plenum $plenum */
    $plenum = $this->app->make(Plenum::class);

    expect($plenum->driver('database')->ping('db_1'))->toBeTrue();

    // Register a sibling connection pointing at a dead port so ping() exercises the failure path.
    $this->setConfig([
        'database.connections.dead' => [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 1,
            'database' => 'plenum_test',
            'username' => 'root',
            'password' => 'plenum',
            'charset' => 'utf8mb4',
            'prefix' => '',
            'options' => [PDO::ATTR_TIMEOUT => 1],
        ],
    ]);

    expect($plenum->driver('database')->ping('dead'))->toBeFalse();
});

it('a write through the activated node lands on that node only', function () {
    /** @var Plenum $plenum */
    $plenum = $this->app->make(Plenum::class);

    $key = 'marker-'.uniqid('', true);

    $plenum->driver('database')->activate('db_1');
    DB::table('marker')->insert(['key_name' => $key]);

    expect(DB::connection('db_1')->table('marker')->where('key_name', $key)->exists())->toBeTrue()
        ->and(DB::connection('db_2')->table('marker')->where('key_name', $key)->exists())->toBeFalse();
});

it('execute() retries on the healthy node when the first candidate is unreachable, and fires FailoverOccurred', function () {
    // Replace the pool with a dead node + a live node. We force a key whose primary
    // candidate is the dead one so execute() has to fail over.
    $this->setConfig([
        'plenum.drivers.database.nodes' => [
            'dead' => ['host' => '127.0.0.1', 'port' => 1],
            'db_1' => ['host' => '127.0.0.1', 'port' => 33061],
        ],
        'plenum.drivers.database.connection_template' => [
            'driver' => 'mysql',
            'database' => 'plenum_test',
            'username' => 'root',
            'password' => 'plenum',
            'charset' => 'utf8mb4',
            'prefix' => '',
            'options' => [PDO::ATTR_TIMEOUT => 1],
        ],
    ]);

    /** @var Plenum $plenum */
    $plenum = $this->rebuildPlenum();

    // Find a key whose primary candidate is the dead node — failover only triggers when
    // execute() actually tries 'dead' first.
    $key = null;
    foreach (range(1, 200) as $i) {
        if ($plenum->candidatesFor('database', "fk:{$i}", 2)[0] === 'dead') {
            $key = "fk:{$i}";
            break;
        }
    }
    expect($key)->not->toBeNull('expected at least one key in 200 tries to hash dead first');

    $captured = null;
    Event::listen(FailoverOccurred::class, function (FailoverOccurred $e) use (&$captured) {
        $captured = $e;
    });

    $marker = 'failover-'.uniqid('', true);
    $result = $plenum->execute('database', $key, function () use ($marker): bool {
        return DB::table('marker')->insert(['key_name' => $marker]);
    });

    expect($result)->toBeTrue()
        ->and(DB::connection('db_1')->table('marker')->where('key_name', $marker)->exists())->toBeTrue()
        ->and($captured)->not->toBeNull()
        ->and($captured->driver)->toBe('database')
        ->and($captured->fromNode)->toBe('dead')
        ->and($captured->toNode)->toBe('db_1');
});
