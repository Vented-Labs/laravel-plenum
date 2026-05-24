<?php

declare(strict_types=1);

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Vented\Plenum\Console\ProbeCommand;
use Vented\Plenum\Contracts\ConnectionDriver;
use Vented\Plenum\Contracts\HealthChecker;
use Vented\Plenum\Drivers\NullDriver;
use Vented\Plenum\Plenum;

afterEach(function () {
    ProbeCommand::$sleeper = null;
    Mockery::close();
});

function configureDrivers(): void
{
    /** @var ConfigRepository $config */
    $config = app(ConfigRepository::class);
    $config->set('plenum.drivers.database.nodes', [
        'db_1' => ['host' => 'h1', 'port' => 5432],
        'db_2' => ['host' => 'h2', 'port' => 5432],
    ]);
    app()->forgetInstance(Plenum::class);
}

// --- plenum:diagnose ---------------------------------------------------------

it('plenum:diagnose warns when no drivers are registered', function () {
    $this->artisan('plenum:diagnose')
        ->expectsOutputToContain('Plenum has no drivers registered')
        ->assertSuccessful();
});

it('plenum:diagnose lists each driver, its nodes, and a status column', function () {
    configureDrivers();

    $this->artisan('plenum:diagnose')
        ->expectsOutputToContain('Strategy')
        ->expectsOutputToContain('Driver:')
        ->expectsOutputToContain('db_1')
        ->expectsOutputToContain('db_2')
        ->assertSuccessful();
});

it('plenum:diagnose renders a "down" badge for nodes marked unhealthy', function () {
    configureDrivers();

    $this->app->make(HealthChecker::class)->markDown('database', 'db_2');

    $this->artisan('plenum:diagnose')
        ->expectsOutputToContain('down')
        ->assertSuccessful();
});

// --- plenum:distribution -----------------------------------------------------

it('plenum:distribution warns when no drivers are registered', function () {
    $this->artisan('plenum:distribution')
        ->expectsOutputToContain('Plenum has no drivers registered')
        ->assertSuccessful();
});

it('plenum:distribution buckets samples across nodes', function () {
    configureDrivers();

    $this->artisan('plenum:distribution', ['--samples' => 100, '--prefix' => 'test'])
        ->expectsOutputToContain('Driver: database')
        ->expectsOutputToContain('db_1')
        ->expectsOutputToContain('db_2')
        ->assertSuccessful();
});

it('plenum:distribution can be restricted to a single driver', function () {
    configureDrivers();

    $this->artisan('plenum:distribution', ['driver' => 'database', '--samples' => 50])
        ->expectsOutputToContain('Driver: database')
        ->assertSuccessful();
});

it('plenum:distribution shows a dash share when zero samples are requested', function () {
    configureDrivers();

    $this->artisan('plenum:distribution', ['--samples' => 0])
        ->expectsOutputToContain('Driver: database')
        ->assertSuccessful();
});

it('plenum:distribution still works when a node has been marked down (unroutable keys swallowed)', function () {
    configureDrivers();

    $this->app->make(HealthChecker::class)->markDown('database', 'db_1');
    $this->app->make(HealthChecker::class)->markDown('database', 'db_2');

    $this->artisan('plenum:distribution', ['--samples' => 10])
        ->assertSuccessful();
});

// --- plenum:probe ------------------------------------------------------------

it('plenum:probe warns when no drivers are registered', function () {
    $this->artisan('plenum:probe')
        ->expectsOutputToContain('Plenum has no drivers registered')
        ->assertSuccessful();
});

it('plenum:probe returns success when every node passes its ping', function () {
    // Register a custom Plenum bound with NullDrivers (NullDriver::ping returns true).
    $this->app->singleton(Plenum::class, function ($app) {
        $plenum = new Plenum(
            strategy: $app->make(\Vented\Plenum\Contracts\RoutingStrategy::class),
            health: $app->make(HealthChecker::class),
            events: $app->make(\Illuminate\Contracts\Events\Dispatcher::class),
        );
        $plenum->registerDriver(new NullDriver('database', ['db_1', 'db_2']));

        return $plenum;
    });

    $this->artisan('plenum:probe')
        ->expectsOutputToContain('database/db_1')
        ->expectsOutputToContain('database/db_2')
        ->assertSuccessful();
});

it('plenum:probe returns FAILURE when any ping returns false', function () {
    $driver = Mockery::mock(ConnectionDriver::class);
    $driver->shouldReceive('name')->andReturn('database');
    $driver->shouldReceive('nodes')->andReturn(['db_1', 'db_2']);
    $driver->shouldReceive('failoverExceptions')->andReturn([]);
    $driver->shouldReceive('ping')->with('db_1')->andReturnTrue();
    $driver->shouldReceive('ping')->with('db_2')->andReturnFalse();

    $this->app->singleton(Plenum::class, function ($app) use ($driver) {
        $plenum = new Plenum(
            strategy: $app->make(\Vented\Plenum\Contracts\RoutingStrategy::class),
            health: $app->make(HealthChecker::class),
            events: $app->make(\Illuminate\Contracts\Events\Dispatcher::class),
        );
        $plenum->registerDriver($driver);

        return $plenum;
    });

    $this->artisan('plenum:probe')->assertFailed();
});

it('plenum:probe --watch runs until max-cycles, calling the sleeper between cycles', function () {
    $this->app->singleton(Plenum::class, function ($app) {
        $plenum = new Plenum(
            strategy: $app->make(\Vented\Plenum\Contracts\RoutingStrategy::class),
            health: $app->make(HealthChecker::class),
            events: $app->make(\Illuminate\Contracts\Events\Dispatcher::class),
        );
        $plenum->registerDriver(new NullDriver('database', ['db_1']));

        return $plenum;
    });

    $sleeps = 0;
    ProbeCommand::$sleeper = function (int $seconds) use (&$sleeps) {
        $sleeps++;
    };

    $this->artisan('plenum:probe', ['--watch' => true, '--max-cycles' => 3, '--interval' => 1])
        ->assertSuccessful();

    // 3 cycles → 2 sleeps between them (the last cycle returns before sleeping).
    expect($sleeps)->toBe(2);
});
