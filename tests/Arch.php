<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Facade;
use Vented\Plenum\Contracts\ConnectionDriver;
use Vented\Plenum\Contracts\RoutingStrategy;

arch('bans debug and legacy PHP helpers')
    ->preset()
    ->php();

arch('bans insecure stdlib functions')
    ->preset()
    ->security();

arch('src declares strict_types=1')
    ->expect('Vented\Plenum')
    ->toUseStrictTypes();

arch('src does not depend on the test namespace')
    ->expect('Vented\Plenum')
    ->not->toUse('Vented\Plenum\Tests');

arch('contracts are interfaces')
    ->expect('Vented\Plenum\Contracts')
    ->toBeInterfaces();

arch('events are final readonly value objects')
    ->expect('Vented\Plenum\Events')
    ->toBeFinal()
    ->toBeReadonly();

arch('drivers implement the contract, are suffixed, and final')
    ->expect('Vented\Plenum\Drivers')
    ->toImplement(ConnectionDriver::class)
    ->toHaveSuffix('Driver')
    ->toBeFinal();

arch('strategies implement the contract, are suffixed, and final')
    ->expect('Vented\Plenum\Strategies')
    ->toImplement(RoutingStrategy::class)
    ->toHaveSuffix('Strategy')
    ->toBeFinal();

arch('exceptions extend RuntimeException and are suffixed')
    ->expect('Vented\Plenum\Exceptions')
    ->toExtend(RuntimeException::class)
    ->toHaveSuffix('Exception');

arch('console commands extend Command and are suffixed')
    ->expect('Vented\Plenum\Console')
    ->toExtend(Command::class)
    ->toHaveSuffix('Command');

arch('facades extend the Laravel Facade base')
    ->expect('Vented\Plenum\Facades')
    ->toExtend(Facade::class);

arch('test doubles are final and prefixed Fake')
    ->expect('Vented\Plenum\Testing')
    ->toBeFinal()
    ->toHavePrefix('Fake');

arch('concrete classes are final')
    ->expect('Vented\Plenum')
    ->classes()
    ->toBeFinal()
    ->ignoring([
        'Vented\Plenum\PlenumServiceProvider',
        'Vented\Plenum\Facades\Plenum',
    ]);
