<?php

declare(strict_types=1);

use Vented\Plenum\Contracts\ConnectionDriver;
use Vented\Plenum\Testing\FakeHealthChecker;

afterEach(fn () => Mockery::close());

it('treats every node as healthy by default', function () {
    $checker = new FakeHealthChecker();

    expect($checker->isHealthy('database', 'db_1'))->toBeTrue()
        ->and($checker->filterHealthy('database', ['db_1', 'db_2']))->toBe(['db_1', 'db_2']);
});

it('accepts a pre-marked-down set via the constructor', function () {
    $checker = new FakeHealthChecker(['database:db_2']);

    expect($checker->isHealthy('database', 'db_1'))->toBeTrue()
        ->and($checker->isHealthy('database', 'db_2'))->toBeFalse()
        ->and($checker->filterHealthy('database', ['db_1', 'db_2']))->toBe(['db_1']);
});

it('markDown and markUp toggle the state without dispatching events', function () {
    $checker = new FakeHealthChecker();

    $checker->markDown('database', 'db_1');
    expect($checker->isHealthy('database', 'db_1'))->toBeFalse();

    $checker->markUp('database', 'db_1');
    expect($checker->isHealthy('database', 'db_1'))->toBeTrue();
});

it('probe delegates to the driver ping and updates state on success', function () {
    $checker = new FakeHealthChecker(['database:db_1']);

    $driver = Mockery::mock(ConnectionDriver::class);
    $driver->shouldReceive('ping')->with('db_1')->andReturnTrue();

    $status = $checker->probe('database', 'db_1', $driver);

    expect($status->healthy)->toBeTrue()
        ->and($status->reason)->toBeNull()
        ->and($checker->isHealthy('database', 'db_1'))->toBeTrue();
});

it('probe delegates to the driver ping and updates state on failure', function () {
    $checker = new FakeHealthChecker();

    $driver = Mockery::mock(ConnectionDriver::class);
    $driver->shouldReceive('ping')->with('db_1')->andReturnFalse();

    $status = $checker->probe('database', 'db_1', $driver);

    expect($status->healthy)->toBeFalse()
        ->and($status->reason)->toBe('fake ping returned false')
        ->and($checker->isHealthy('database', 'db_1'))->toBeFalse();
});

it('markDown ignores the optional throwable reason', function () {
    $checker = new FakeHealthChecker();

    $checker->markDown('database', 'db_1', new RuntimeException('explanation'));

    expect($checker->isHealthy('database', 'db_1'))->toBeFalse();
});

it('preserves order when filtering', function () {
    $checker = new FakeHealthChecker(['database:db_2']);

    expect($checker->filterHealthy('database', ['db_1', 'db_2', 'db_3']))->toBe(['db_1', 'db_3']);
});
