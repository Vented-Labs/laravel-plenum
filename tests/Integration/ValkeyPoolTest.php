<?php

declare(strict_types=1);

namespace Vented\Plenum\Tests\Integration;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Vented\Plenum\Drivers\RedisDriver;
use Vented\Plenum\Events\FailoverOccurred;
use Vented\Plenum\Facades\Plenum as PlenumFacade;
use Vented\Plenum\Plenum;

afterEach(function () {
    Event::forget(FailoverOccurred::class);
});

beforeEach(function () {
    $this->requireBackends(['valkey_1', 'valkey_2']);

    $this->setConfig([
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

    $this->rebuildPlenum();

    foreach (['r_1', 'r_2'] as $node) {
        Redis::connection($node)->command('flushdb');
    }
});

it('activate() binds the active node and Plenum::redis() answers PING', function () {
    /** @var Plenum $plenum */
    $plenum = $this->app->make(Plenum::class);

    $plenum->driver('redis')->activate('r_2');

    expect($this->app->make(RedisDriver::ACTIVE_BINDING))->toBe('r_2')
        ->and(PlenumFacade::redis()->command('ping'))->toBeIn([true, 'PONG', '+PONG']);
});

it('nodeFor() is deterministic for a given key', function () {
    /** @var Plenum $plenum */
    $plenum = $this->app->make(Plenum::class);

    expect($plenum->nodeFor('redis', 'tenant-42'))
        ->toBe($plenum->nodeFor('redis', 'tenant-42'))
        ->toBe($plenum->nodeFor('redis', 'tenant-42'));
});

it('ping() returns true for live nodes and false for an unreachable host', function () {
    /** @var Plenum $plenum */
    $plenum = $this->app->make(Plenum::class);

    expect($plenum->driver('redis')->ping('r_1'))->toBeTrue();

    $this->setConfig([
        'database.redis.dead' => [
            'host' => '127.0.0.1',
            'port' => 1,
            'database' => 0,
            'read_write_timeout' => 1,
        ],
    ]);

    expect($plenum->driver('redis')->ping('dead'))->toBeFalse();
});

it('a SET through the routed connection lands on that node only', function () {
    /** @var Plenum $plenum */
    $plenum = $this->app->make(Plenum::class);

    $plenum->driver('redis')->activate('r_1');

    $key = 'plenum:test:'.uniqid('', true);

    PlenumFacade::redis()->command('set', [$key, 'one']);

    // phpredis returns false for missing keys; predis returns null. Either is "absent".
    expect(Redis::connection('r_1')->command('get', [$key]))->toBe('one')
        ->and(Redis::connection('r_2')->command('get', [$key]))->toBeIn([false, null]);
});

it('execute() retries on the healthy node when the first candidate is unreachable, and fires FailoverOccurred', function () {
    $this->setConfig([
        'plenum.drivers.redis.nodes' => [
            'dead' => ['host' => '127.0.0.1', 'port' => 1],
            'r_1' => ['host' => '127.0.0.1', 'port' => 63791],
        ],
        'plenum.drivers.redis.connection_template' => [
            'database' => 0,
            'password' => null,
            'read_write_timeout' => 1,
        ],
    ]);

    /** @var Plenum $plenum */
    $plenum = $this->rebuildPlenum();

    $key = null;
    foreach (range(1, 200) as $i) {
        if ($plenum->candidatesFor('redis', "fk:{$i}", 2)[0] === 'dead') {
            $key = "fk:{$i}";
            break;
        }
    }
    expect($key)->not->toBeNull('expected at least one key in 200 tries to hash dead first');

    $captured = null;
    Event::listen(FailoverOccurred::class, function (FailoverOccurred $e) use (&$captured) {
        $captured = $e;
    });

    $marker = 'plenum:failover:'.uniqid('', true);
    $result = $plenum->execute('redis', $key, function () use ($marker): string {
        PlenumFacade::redis()->command('set', [$marker, 'ok']);

        return $marker;
    });

    expect($result)->toBe($marker)
        ->and(Redis::connection('r_1')->command('get', [$marker]))->toBe('ok')
        ->and($captured)->not->toBeNull()
        ->and($captured->driver)->toBe('redis')
        ->and($captured->fromNode)->toBe('dead')
        ->and($captured->toNode)->toBe('r_1');
});
