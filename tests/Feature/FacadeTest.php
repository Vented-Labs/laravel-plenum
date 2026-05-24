<?php

declare(strict_types=1);

use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection as RedisConnection;
use Vented\Plenum\Drivers\RedisDriver;
use Vented\Plenum\Facades\Plenum;
use Vented\Plenum\Plenum as PlenumCore;

afterEach(fn () => Mockery::close());

it('proxies static calls to the singleton', function () {
    expect(Plenum::strategy())->toBeInstanceOf(\Vented\Plenum\Contracts\RoutingStrategy::class)
        ->and(Plenum::drivers())->toBeArray()
        ->and(Plenum::getFacadeRoot())->toBeInstanceOf(PlenumCore::class);
});

it('redis() resolves the active routed Redis connection', function () {
    $connection = Mockery::mock(RedisConnection::class);
    $factory = Mockery::mock(RedisFactory::class);
    $factory->shouldReceive('connection')->with('redis_2')->andReturn($connection);

    $this->app->instance(RedisFactory::class, $factory);
    $this->app->instance(RedisDriver::ACTIVE_BINDING, 'redis_2');

    expect(Plenum::redis())->toBe($connection);
});

it('redis() throws when no Redis routing has been activated', function () {
    expect(fn () => Plenum::redis())
        ->toThrow(RuntimeException::class, 'has not routed a Redis connection');
});
