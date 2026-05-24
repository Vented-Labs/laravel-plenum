<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Events\Dispatcher;
use Vented\Plenum\Contracts\ConnectionDriver;
use Vented\Plenum\Events\FailoverOccurred;
use Vented\Plenum\Events\NodeMarkedDown;
use Vented\Plenum\Events\NodeRecovered;
use Vented\Plenum\Health\NodeStatus;
use Vented\Plenum\Health\PingHealthChecker;

afterEach(fn () => Mockery::close());

function makeChecker(): array
{
    $cache = new CacheRepository(new ArrayStore());
    $events = new Dispatcher();

    return [new PingHealthChecker($cache, $events), $cache, $events];
}

it('treats unknown (cold-cache) nodes as healthy', function () {
    [$checker] = makeChecker();

    expect($checker->isHealthy('database', 'db_1'))->toBeTrue();
});

it('marks a node down and reads it back as unhealthy', function () {
    [$checker] = makeChecker();

    $checker->markDown('database', 'db_1');

    expect($checker->isHealthy('database', 'db_1'))->toBeFalse();
});

it('marks a node up and reads it back as healthy', function () {
    [$checker] = makeChecker();

    $checker->markDown('database', 'db_1');
    $checker->markUp('database', 'db_1');

    expect($checker->isHealthy('database', 'db_1'))->toBeTrue();
});

it('filterHealthy drops nodes that are marked down', function () {
    [$checker] = makeChecker();

    $checker->markDown('database', 'db_2');

    expect($checker->filterHealthy('database', ['db_1', 'db_2', 'db_3']))
        ->toBe(['db_1', 'db_3']);
});

it('filterHealthy preserves order and reindexes', function () {
    [$checker] = makeChecker();

    $checker->markDown('database', 'db_1');

    expect($checker->filterHealthy('database', ['db_1', 'db_2', 'db_3']))
        ->toBe(['db_2', 'db_3']);
});

it('dispatches NodeMarkedDown only on first transition from up to down', function () {
    [$checker, , $events] = makeChecker();

    $fired = [];
    $events->listen(NodeMarkedDown::class, function (NodeMarkedDown $e) use (&$fired) {
        $fired[] = $e;
    });

    $checker->markDown('database', 'db_1', new RuntimeException('connection refused'));
    $checker->markDown('database', 'db_1', new RuntimeException('still down'));

    expect($fired)->toHaveCount(1)
        ->and($fired[0]->driver)->toBe('database')
        ->and($fired[0]->node)->toBe('db_1')
        ->and($fired[0]->reason)->toBe('connection refused');
});

it('NodeMarkedDown reason is null when no throwable is given', function () {
    [$checker, , $events] = makeChecker();

    $captured = null;
    $events->listen(NodeMarkedDown::class, function (NodeMarkedDown $e) use (&$captured) {
        $captured = $e;
    });

    $checker->markDown('database', 'db_1');

    expect($captured)->not->toBeNull()
        ->and($captured->reason)->toBeNull();
});

it('dispatches NodeRecovered only when transitioning from down to up', function () {
    [$checker, , $events] = makeChecker();

    $fired = [];
    $events->listen(NodeRecovered::class, function (NodeRecovered $e) use (&$fired) {
        $fired[] = $e;
    });

    $checker->markDown('database', 'db_1');
    $checker->markUp('database', 'db_1');
    $checker->markUp('database', 'db_1'); // already up, no event

    expect($fired)->toHaveCount(1);
});

it('does not dispatch NodeRecovered for a cold node that is marked up', function () {
    [$checker, , $events] = makeChecker();

    $fired = false;
    $events->listen(NodeRecovered::class, function () use (&$fired) {
        $fired = true;
    });

    $checker->markUp('database', 'db_1');

    expect($fired)->toBeFalse();
});

it('probe returns a NodeStatus and marks the node up on a successful ping', function () {
    [$checker] = makeChecker();

    $driver = Mockery::mock(ConnectionDriver::class);
    $driver->shouldReceive('ping')->with('db_1')->andReturnTrue();

    $status = $checker->probe('database', 'db_1', $driver);

    expect($status)->toBeInstanceOf(NodeStatus::class)
        ->and($status->driver)->toBe('database')
        ->and($status->node)->toBe('db_1')
        ->and($status->healthy)->toBeTrue()
        ->and($status->reason)->toBeNull()
        ->and($checker->isHealthy('database', 'db_1'))->toBeTrue();
});

it('probe marks the node down and reports a reason on a failed ping', function () {
    [$checker] = makeChecker();

    $driver = Mockery::mock(ConnectionDriver::class);
    $driver->shouldReceive('ping')->with('db_1')->andReturnFalse();

    $status = $checker->probe('database', 'db_1', $driver);

    expect($status->healthy)->toBeFalse()
        ->and($status->reason)->toBe('ping returned false')
        ->and($checker->isHealthy('database', 'db_1'))->toBeFalse();
});

it('probe dispatches NodeMarkedDown when a previously-up node fails the ping', function () {
    [$checker, , $events] = makeChecker();

    $fired = false;
    $events->listen(NodeMarkedDown::class, function () use (&$fired) {
        $fired = true;
    });

    $driver = Mockery::mock(ConnectionDriver::class);
    $driver->shouldReceive('ping')->andReturnFalse();

    $checker->probe('database', 'db_1', $driver);

    expect($fired)->toBeTrue();
});

it('uses a custom cache prefix in the keys it writes', function () {
    $cache = new CacheRepository(new ArrayStore());
    $checker = new PingHealthChecker($cache, new Dispatcher(), cachePrefix: 'custom:');
    $checker->markDown('database', 'db_1');

    expect($cache->get('custom:database:db_1'))->toBe('down');
});

it('respects healthyTtlSeconds and downTtlSeconds when writing to the cache', function () {
    $cache = Mockery::mock(CacheRepository::class);
    $cache->shouldReceive('get')->andReturn(null);
    $cache->shouldReceive('put')->with('plenum:health:database:db_1', 'up', 99)->once();
    $cache->shouldReceive('put')->with('plenum:health:database:db_2', 'down', 7)->once();

    $checker = new PingHealthChecker(
        $cache,
        new Dispatcher(),
        healthyTtlSeconds: 99,
        downTtlSeconds: 7,
    );

    $checker->markUp('database', 'db_1');
    $checker->markDown('database', 'db_2');
});

it('FailoverOccurred event carries the from/to nodes and the cause', function () {
    $cause = new RuntimeException('node went away');
    $event = new FailoverOccurred(
        driver: 'database',
        fromNode: 'db_1',
        toNode: 'db_2',
        reason: $cause,
    );

    expect($event->driver)->toBe('database')
        ->and($event->fromNode)->toBe('db_1')
        ->and($event->toNode)->toBe('db_2')
        ->and($event->reason)->toBe($cause);
});
