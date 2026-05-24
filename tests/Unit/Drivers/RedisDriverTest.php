<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Contracts\Redis\Connection as RedisConnection;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Vented\Plenum\Drivers\RedisDriver;

afterEach(fn () => Mockery::close());

it('exposes its configured name and nodes', function () {
    $driver = new RedisDriver(
        Mockery::mock(RedisFactory::class),
        new Container(),
        ['redis_1', 'redis_2'],
        'cache',
    );

    expect($driver->name())->toBe('cache')
        ->and($driver->nodes())->toBe(['redis_1', 'redis_2']);
});

it('defaults the name to "redis"', function () {
    $driver = new RedisDriver(
        Mockery::mock(RedisFactory::class),
        new Container(),
        ['redis_1'],
    );

    expect($driver->name())->toBe('redis');
});

it('reindexes the node list', function () {
    $driver = new RedisDriver(
        Mockery::mock(RedisFactory::class),
        new Container(),
        [10 => 'redis_1', 30 => 'redis_2'],
    );

    expect($driver->nodes())->toBe(['redis_1', 'redis_2']);
});

it('activate() binds the active node onto the container', function () {
    $container = new Container();

    (new RedisDriver(
        Mockery::mock(RedisFactory::class),
        $container,
        ['redis_1', 'redis_2'],
    ))->activate('redis_2');

    expect($container->make(RedisDriver::ACTIVE_BINDING))->toBe('redis_2');
});

it('deactivate() forgets the active node binding', function () {
    $container = new Container();
    $driver = new RedisDriver(
        Mockery::mock(RedisFactory::class),
        $container,
        ['redis_1', 'redis_2'],
    );

    $driver->activate('redis_2');
    $driver->deactivate();

    expect($container->bound(RedisDriver::ACTIVE_BINDING))->toBeFalse();
});

it('deactivate() is safe when nothing was ever activated', function () {
    $container = new Container();
    $driver = new RedisDriver(
        Mockery::mock(RedisFactory::class),
        $container,
        ['redis_1'],
    );

    $driver->deactivate();

    expect($container->bound(RedisDriver::ACTIVE_BINDING))->toBeFalse();
});

it('ping() calls ping on the resolved connection and returns true on success', function () {
    $connection = Mockery::mock(RedisConnection::class);
    $connection->shouldReceive('ping')->once()->andReturnTrue();

    $factory = Mockery::mock(RedisFactory::class);
    $factory->shouldReceive('connection')->with('redis_1')->andReturn($connection);

    $driver = new RedisDriver($factory, new Container(), ['redis_1']);

    expect($driver->ping('redis_1'))->toBeTrue();
});

it('ping() returns false when ping throws', function () {
    $connection = Mockery::mock(RedisConnection::class);
    $connection->shouldReceive('ping')->andThrow(new RuntimeException('redis down'));

    $factory = Mockery::mock(RedisFactory::class);
    $factory->shouldReceive('connection')->with('redis_1')->andReturn($connection);

    $driver = new RedisDriver($factory, new Container(), ['redis_1']);

    expect($driver->ping('redis_1'))->toBeFalse();
});

it('ping() returns false when resolving the connection throws', function () {
    $factory = Mockery::mock(RedisFactory::class);
    $factory->shouldReceive('connection')->andThrow(new RuntimeException('no such connection'));

    $driver = new RedisDriver($factory, new Container(), ['redis_1']);

    expect($driver->ping('redis_1'))->toBeFalse();
});

it('declares the common phpredis and predis failover exceptions', function () {
    $driver = new RedisDriver(
        Mockery::mock(RedisFactory::class),
        new Container(),
        ['redis_1'],
    );

    expect($driver->failoverExceptions())->toBe([
        'RedisException',
        'Predis\\Connection\\ConnectionException',
        'Predis\\CommunicationException',
    ]);
});
