<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Events\Dispatcher;
use Vented\Plenum\Contracts\ConnectionDriver;
use Vented\Plenum\Contracts\RoutingStrategy;
use Vented\Plenum\Events\FailoverOccurred;
use Vented\Plenum\Exceptions\ConfigurationException;
use Vented\Plenum\Exceptions\NoHealthyNodesException;
use Vented\Plenum\Health\PingHealthChecker;
use Vented\Plenum\Plenum;
use Vented\Plenum\Strategies\CallbackStrategy;

afterEach(fn () => Mockery::close());

/**
 * @return array{Plenum, PingHealthChecker, Dispatcher}
 */
function makePlenum(?RoutingStrategy $strategy = null, int $maxFailover = 3): array
{
    $cache = new CacheRepository(new ArrayStore());
    $events = new Dispatcher();
    $health = new PingHealthChecker($cache, $events);
    $strategy ??= new CallbackStrategy(fn () => 'user:42');

    return [new Plenum($strategy, $health, $events, maxFailoverAttempts: $maxFailover), $health, $events];
}

/**
 * @param  array<int, string>  $nodes
 * @param  array<int, string>  $failoverExceptions
 */
function makeDriver(
    array $nodes = ['n1', 'n2', 'n3'],
    string $name = 'database',
    array $failoverExceptions = [RuntimeException::class],
): ConnectionDriver {
    $driver = Mockery::mock(ConnectionDriver::class);
    $driver->shouldReceive('name')->andReturn($name);
    $driver->shouldReceive('nodes')->andReturn($nodes);
    $driver->shouldReceive('failoverExceptions')->andReturn($failoverExceptions);

    return $driver;
}

// --- strategy + driver accessors --------------------------------------------

it('exposes its strategy', function () {
    [$plenum] = makePlenum(new CallbackStrategy(fn () => 'a', 'first'));

    expect($plenum->strategy()->name())->toBe('first');
});

it('starts with no drivers and accumulates them via registerDriver', function () {
    [$plenum] = makePlenum();

    expect($plenum->drivers())->toBe([]);

    $plenum->registerDriver(makeDriver(name: 'database'));
    $plenum->registerDriver(makeDriver(name: 'redis'));

    expect(array_keys($plenum->drivers()))->toBe(['database', 'redis']);
});

it('driver() returns the registered instance and throws otherwise', function () {
    [$plenum] = makePlenum();
    $driver = makeDriver(name: 'database');
    $plenum->registerDriver($driver);

    expect($plenum->driver('database'))->toBe($driver);

    $plenum->driver('missing');
})->throws(ConfigurationException::class, '"missing"');

// --- routeCurrentRequest -----------------------------------------------------

it('returns empty when the strategy yields no key', function () {
    [$plenum] = makePlenum(new CallbackStrategy(fn () => null));
    $driver = makeDriver();
    $driver->shouldNotReceive('activate');
    $plenum->registerDriver($driver);

    expect($plenum->routeCurrentRequest())->toBe([]);
});

it('deactivates every driver at the start of routeCurrentRequest', function () {
    [$plenum] = makePlenum();
    $driver = makeDriver(['n1', 'n2'], 'database');
    $driver->shouldReceive('deactivate')->once();
    $driver->shouldReceive('activate')->once();
    $plenum->registerDriver($driver);

    $plenum->routeCurrentRequest();
});

it('deactivates drivers even when the strategy yields no key', function () {
    [$plenum] = makePlenum(new CallbackStrategy(fn () => null));
    $driver = makeDriver();
    $driver->shouldReceive('deactivate')->once();
    $driver->shouldNotReceive('activate');
    $plenum->registerDriver($driver);

    expect($plenum->routeCurrentRequest())->toBe([]);
});

it('isolates per-driver deactivate failures so other drivers still route', function () {
    [$plenum] = makePlenum();

    $broken = Mockery::mock(ConnectionDriver::class);
    $broken->shouldReceive('name')->andReturn('database');
    $broken->shouldReceive('nodes')->andReturn(['db_1']);
    $broken->shouldReceive('failoverExceptions')->andReturn([]);
    $broken->shouldReceive('deactivate')->once()->andThrow(new RuntimeException('reset blew up'));
    $broken->shouldReceive('activate')->once();
    $plenum->registerDriver($broken);

    $redis = makeDriver(['r_1', 'r_2'], 'redis');
    $redis->shouldReceive('deactivate')->once();
    $redis->shouldReceive('activate')->once();
    $plenum->registerDriver($redis);

    $result = $plenum->routeCurrentRequest();

    expect($result)->toHaveKey('redis');
});

it('returns empty when no drivers are registered', function () {
    [$plenum] = makePlenum();

    expect($plenum->routeCurrentRequest())->toBe([]);
});

it('skips drivers with empty node lists without crashing', function () {
    [$plenum] = makePlenum();
    $empty = makeDriver(nodes: [], name: 'redis');
    $empty->shouldNotReceive('activate');
    $plenum->registerDriver($empty);

    expect($plenum->routeCurrentRequest())->toBe([]);
});

it('activates the resolved node on every registered driver', function () {
    [$plenum] = makePlenum();

    $database = makeDriver(['db_1', 'db_2'], 'database');
    $database->shouldReceive('activate')->once();
    $plenum->registerDriver($database);

    $redis = makeDriver(['r_1', 'r_2'], 'redis');
    $redis->shouldReceive('activate')->once();
    $plenum->registerDriver($redis);

    $result = $plenum->routeCurrentRequest();

    expect(array_keys($result))->toBe(['database', 'redis'])
        ->and($result['database'])->toBeIn(['db_1', 'db_2'])
        ->and($result['redis'])->toBeIn(['r_1', 'r_2']);
});

it('isolates driver failures so the other drivers still route', function () {
    [$plenum] = makePlenum();

    $broken = Mockery::mock(ConnectionDriver::class);
    $broken->shouldReceive('name')->andReturn('database');
    $broken->shouldReceive('nodes')->andReturn(['db_1']);
    $broken->shouldReceive('activate')->andThrow(new RuntimeException('node went away'));
    $broken->shouldReceive('failoverExceptions')->andReturn([]);
    $plenum->registerDriver($broken);

    $redis = makeDriver(['r_1', 'r_2'], 'redis');
    $redis->shouldReceive('activate')->once();
    $plenum->registerDriver($redis);

    $result = $plenum->routeCurrentRequest();

    expect($result)->toHaveKey('redis')
        ->and($result)->not->toHaveKey('database');
});

it('routes the same key to the same node deterministically', function () {
    [$plenum] = makePlenum();
    $driver = makeDriver(['n1', 'n2', 'n3', 'n4', 'n5']);
    $driver->shouldReceive('activate')->atLeast()->once();
    $plenum->registerDriver($driver);

    $first = $plenum->routeCurrentRequest();
    $second = $plenum->routeCurrentRequest();

    expect($first)->toBe($second);
});

// --- nodeFor / candidatesFor -------------------------------------------------

it('nodeFor returns a stable node from the driver pool', function () {
    [$plenum] = makePlenum();
    $plenum->registerDriver(makeDriver(['n1', 'n2', 'n3']));

    expect($plenum->nodeFor('database', 'user:42'))->toBeIn(['n1', 'n2', 'n3']);
});

it('nodeFor throws NoHealthyNodes when health filter empties the list', function () {
    [$plenum, $health] = makePlenum();
    $plenum->registerDriver(makeDriver(['n1', 'n2']));

    $health->markDown('database', 'n1');
    $health->markDown('database', 'n2');

    $plenum->nodeFor('database', 'k');
})->throws(NoHealthyNodesException::class, 'database');

it('candidatesFor returns up to count nodes', function () {
    [$plenum] = makePlenum();
    $plenum->registerDriver(makeDriver(['n1', 'n2', 'n3', 'n4']));

    $list = $plenum->candidatesFor('database', 'user:42', 3);

    expect($list)->toHaveCount(3)
        ->and(array_unique($list))->toHaveCount(3);
});

it('candidatesFor throws NoHealthyNodes when health filter empties the list', function () {
    [$plenum, $health] = makePlenum();
    $plenum->registerDriver(makeDriver(['n1']));

    $health->markDown('database', 'n1');

    $plenum->candidatesFor('database', 'k', 2);
})->throws(NoHealthyNodesException::class);

// --- execute -----------------------------------------------------------------

it('execute() returns the callback result on the first try', function () {
    [$plenum] = makePlenum();
    $driver = makeDriver(['n1', 'n2']);
    $driver->shouldReceive('activate')->once();
    $plenum->registerDriver($driver);

    $result = $plenum->execute('database', 'k', fn (string $node) => "got:{$node}");

    expect($result)->toStartWith('got:');
});

it('execute() retries on failover exception and dispatches FailoverOccurred on success', function () {
    [$plenum, , $events] = makePlenum();

    $driver = makeDriver(['n1', 'n2', 'n3']);
    $driver->shouldReceive('activate')->times(2);
    $plenum->registerDriver($driver);

    $fired = [];
    $events->listen(FailoverOccurred::class, function (FailoverOccurred $e) use (&$fired) {
        $fired[] = $e;
    });

    $calls = 0;
    $result = $plenum->execute('database', 'k', function (string $node) use (&$calls) {
        $calls++;
        if ($calls === 1) {
            throw new RuntimeException('first node sad');
        }

        return "served-from:{$node}";
    });

    expect($result)->toStartWith('served-from:')
        ->and($calls)->toBe(2)
        ->and($fired)->toHaveCount(1)
        ->and($fired[0]->driver)->toBe('database')
        ->and($fired[0]->reason->getMessage())->toBe('first node sad');
});

it('execute() throws when all candidates fail with a failover exception', function () {
    [$plenum] = makePlenum(maxFailover: 2);
    $driver = makeDriver(['n1', 'n2', 'n3']);
    $driver->shouldReceive('activate')->times(2);
    $plenum->registerDriver($driver);

    expect(fn () => $plenum->execute('database', 'k', function () {
        throw new RuntimeException('always-fails');
    }))->toThrow(RuntimeException::class, 'always-fails');
});

it('execute() does not retry on non-failover exceptions', function () {
    [$plenum] = makePlenum();
    $driver = makeDriver(['n1', 'n2'], failoverExceptions: [PDOException::class]);
    $driver->shouldReceive('activate')->once();
    $plenum->registerDriver($driver);

    expect(fn () => $plenum->execute('database', 'k', function () {
        throw new RuntimeException('app bug');
    }))->toThrow(RuntimeException::class, 'app bug');
});

it('execute() marks down each node that throws a failover exception', function () {
    [$plenum, $health] = makePlenum();
    $driver = makeDriver(['n1', 'n2', 'n3']);
    $driver->shouldReceive('activate')->atLeast()->once();
    $plenum->registerDriver($driver);

    $calls = 0;
    $plenum->execute('database', 'k', function (string $node) use (&$calls) {
        $calls++;
        if ($calls < 3) {
            throw new RuntimeException("dead:{$node}");
        }

        return 'ok';
    });

    expect($health->filterHealthy('database', ['n1', 'n2', 'n3']))->toHaveCount(1);
});

it('execute() throws NoHealthyNodes when there are zero healthy nodes', function () {
    [$plenum, $health] = makePlenum();
    $plenum->registerDriver(makeDriver(['n1']));

    $health->markDown('database', 'n1');

    expect(fn () => $plenum->execute('database', 'k', fn () => null))
        ->toThrow(NoHealthyNodesException::class);
});

it('execute() does not fire FailoverOccurred when the first try succeeds', function () {
    [$plenum, , $events] = makePlenum();
    $driver = makeDriver(['n1', 'n2']);
    $driver->shouldReceive('activate')->once();
    $plenum->registerDriver($driver);

    $fired = false;
    $events->listen(FailoverOccurred::class, function () use (&$fired) {
        $fired = true;
    });

    $plenum->execute('database', 'k', fn () => 'ok');

    expect($fired)->toBeFalse();
});

it('execute() can accept a string class-name as a failover exception (for optional packages)', function () {
    [$plenum] = makePlenum();
    $driver = makeDriver(['n1', 'n2'], failoverExceptions: ['RuntimeException', 'Some\\NonExistent\\Class']);
    $driver->shouldReceive('activate')->atLeast()->once();
    $plenum->registerDriver($driver);

    $calls = 0;
    $result = $plenum->execute('database', 'k', function () use (&$calls) {
        $calls++;
        if ($calls === 1) {
            throw new RuntimeException('failover');
        }

        return 'ok';
    });

    expect($result)->toBe('ok');
});

// --- ConfigurationException factories ---------------------------------------

it('ConfigurationException::enabledButEmpty produces a message naming the driver', function () {
    $e = ConfigurationException::enabledButEmpty('redis');

    expect($e->getMessage())->toContain('redis')
        ->and($e->getMessage())->toContain('enabled');
});
