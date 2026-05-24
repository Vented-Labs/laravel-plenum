<?php

declare(strict_types=1);

use Vented\Plenum\Drivers\NullDriver;

it('has default name "null" and empty nodes', function () {
    $driver = new NullDriver();

    expect($driver->name())->toBe('null')
        ->and($driver->nodes())->toBe([])
        ->and($driver->failoverExceptions())->toBe([]);
});

it('accepts a custom name and node list', function () {
    $driver = new NullDriver('shadow', ['n1', 'n2']);

    expect($driver->name())->toBe('shadow')
        ->and($driver->nodes())->toBe(['n1', 'n2']);
});

it('reindexes the node list with array_values', function () {
    $driver = new NullDriver('x', [10 => 'a', 20 => 'b']);

    expect($driver->nodes())->toBe(['a', 'b']);
});

it('activate() is a no-op and ping() always returns true', function () {
    $driver = new NullDriver();

    $driver->activate('whatever');

    expect($driver->ping('whatever'))->toBeTrue();
});

it('deactivate() is a no-op', function () {
    $driver = new NullDriver();

    $driver->deactivate();

    expect($driver->ping('whatever'))->toBeTrue();
});
