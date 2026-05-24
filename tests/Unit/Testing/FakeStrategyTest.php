<?php

declare(strict_types=1);

use Vented\Plenum\Testing\FakeStrategy;

it('returns the configured key', function () {
    expect((new FakeStrategy(42))->resolve())->toBe(42)
        ->and((new FakeStrategy('user:7'))->resolve())->toBe('user:7')
        ->and((new FakeStrategy(null))->resolve())->toBeNull();
});

it('defaults to "fake" name and accepts overrides', function () {
    expect((new FakeStrategy(1))->name())->toBe('fake')
        ->and((new FakeStrategy(1, 'tenant-fixture'))->name())->toBe('tenant-fixture');
});

it('supports setKey for in-test reconfiguration', function () {
    $strategy = new FakeStrategy('one');

    $strategy->setKey('two');

    expect($strategy->resolve())->toBe('two');
});
