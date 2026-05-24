<?php

declare(strict_types=1);

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Vented\Plenum\Drivers\DatabaseDriver;

afterEach(fn () => Mockery::close());

it('exposes its configured name and nodes', function () {
    $db = Mockery::mock(DatabaseManager::class);
    $config = Mockery::mock(ConfigRepository::class);

    $driver = new DatabaseDriver($db, $config, ['db_1', 'db_2'], 'pgedge');

    expect($driver->name())->toBe('pgedge')
        ->and($driver->nodes())->toBe(['db_1', 'db_2']);
});

it('defaults the name to "database"', function () {
    $driver = new DatabaseDriver(
        Mockery::mock(DatabaseManager::class),
        Mockery::mock(ConfigRepository::class),
        ['db_1'],
    );

    expect($driver->name())->toBe('database');
});

it('reindexes the node list', function () {
    $driver = new DatabaseDriver(
        Mockery::mock(DatabaseManager::class),
        Mockery::mock(ConfigRepository::class),
        [5 => 'db_1', 7 => 'db_2'],
    );

    expect($driver->nodes())->toBe(['db_1', 'db_2']);
});

it('activate() sets the default connection and updates config', function () {
    $db = Mockery::mock(DatabaseManager::class);
    $db->shouldReceive('setDefaultConnection')->with('db_2')->once();

    $config = Mockery::mock(ConfigRepository::class);
    $config->shouldReceive('set')->with('database.default', 'db_2')->once();

    (new DatabaseDriver($db, $config, ['db_1', 'db_2']))->activate('db_2');
});

it('ping() runs select 1 against the connection and returns true on success', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('select')->with('select 1')->once()->andReturn([['1' => 1]]);

    $db = Mockery::mock(DatabaseManager::class);
    $db->shouldReceive('connection')->with('db_1')->andReturn($connection);
    $db->shouldNotReceive('purge');

    $driver = new DatabaseDriver($db, Mockery::mock(ConfigRepository::class), ['db_1']);

    expect($driver->ping('db_1'))->toBeTrue();
});

it('ping() returns false and purges the connection when select throws', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('select')->andThrow(new RuntimeException('connection refused'));

    $db = Mockery::mock(DatabaseManager::class);
    $db->shouldReceive('connection')->with('db_1')->andReturn($connection);
    $db->shouldReceive('purge')->with('db_1')->once();

    $driver = new DatabaseDriver($db, Mockery::mock(ConfigRepository::class), ['db_1']);

    expect($driver->ping('db_1'))->toBeFalse();
});

it('ping() returns false when resolving the connection throws', function () {
    $db = Mockery::mock(DatabaseManager::class);
    $db->shouldReceive('connection')->andThrow(new RuntimeException('no such connection'));
    $db->shouldReceive('purge')->with('db_1')->once();

    $driver = new DatabaseDriver($db, Mockery::mock(ConfigRepository::class), ['db_1']);

    expect($driver->ping('db_1'))->toBeFalse();
});

it('declares PDOException as its failover exception', function () {
    $driver = new DatabaseDriver(
        Mockery::mock(DatabaseManager::class),
        Mockery::mock(ConfigRepository::class),
        ['db_1'],
    );

    expect($driver->failoverExceptions())->toBe([PDOException::class]);
});
