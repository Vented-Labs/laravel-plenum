<?php

declare(strict_types=1);

use Vented\Plenum\Exceptions\NoHealthyNodesException;

it('builds a message naming the driver for empty()', function () {
    $exception = NoHealthyNodesException::empty('database');

    expect($exception)->toBeInstanceOf(NoHealthyNodesException::class)
        ->and($exception->getMessage())->toContain('database')
        ->and($exception->getMessage())->toContain('no nodes');
});

it('builds a message naming the driver for forDriver()', function () {
    $exception = NoHealthyNodesException::forDriver('redis');

    expect($exception)->toBeInstanceOf(NoHealthyNodesException::class)
        ->and($exception->getMessage())->toContain('redis')
        ->and($exception->getMessage())->toContain('no healthy');
});
