<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Session\Session;
use Vented\Plenum\Strategies\AuthUserStrategy;
use Vented\Plenum\Strategies\CallbackStrategy;
use Vented\Plenum\Strategies\CompositeStrategy;
use Vented\Plenum\Strategies\SessionOnlyStrategy;
use Vented\Plenum\Strategies\TenantStrategy;

function fakeAuth(callable $guardReturn): AuthFactory
{
    $factory = Mockery::mock(AuthFactory::class);
    $factory->shouldReceive('guard')->andReturnUsing($guardReturn);

    return $factory;
}

function fakeGuard(mixed $id): Guard
{
    $guard = Mockery::mock(Guard::class);
    $guard->shouldReceive('id')->andReturn($id);

    return $guard;
}

afterEach(fn () => Mockery::close());

// --- AuthUserStrategy --------------------------------------------------------

it('AuthUserStrategy returns user id when authenticated', function () {
    $strategy = new AuthUserStrategy(fakeAuth(fn () => fakeGuard(42)));

    expect($strategy->resolve())->toBe(42);
});

it('AuthUserStrategy returns string id unchanged', function () {
    $strategy = new AuthUserStrategy(fakeAuth(fn () => fakeGuard('uuid-1')));

    expect($strategy->resolve())->toBe('uuid-1');
});

it('AuthUserStrategy returns null when not authenticated', function () {
    $strategy = new AuthUserStrategy(fakeAuth(fn () => fakeGuard(null)));

    expect($strategy->resolve())->toBeNull();
});

it('AuthUserStrategy returns null for empty-string ids', function () {
    $strategy = new AuthUserStrategy(fakeAuth(fn () => fakeGuard('')));

    expect($strategy->resolve())->toBeNull();
});

it('AuthUserStrategy returns null for non-scalar ids', function () {
    $strategy = new AuthUserStrategy(fakeAuth(fn () => fakeGuard(3.14)));

    expect($strategy->resolve())->toBeNull();
});

it('AuthUserStrategy swallows guard exceptions', function () {
    $factory = Mockery::mock(AuthFactory::class);
    $factory->shouldReceive('guard')->andThrow(new RuntimeException('boom'));

    expect((new AuthUserStrategy($factory))->resolve())->toBeNull();
});

it('AuthUserStrategy uses specified guard when provided', function () {
    $factory = Mockery::mock(AuthFactory::class);
    $factory->shouldReceive('guard')->with('api')->andReturn(fakeGuard('admin-1'));

    $strategy = new AuthUserStrategy($factory, 'api');

    expect($strategy->resolve())->toBe('admin-1');
});

it('AuthUserStrategy uses the default guard when none specified', function () {
    $factory = Mockery::mock(AuthFactory::class);
    $factory->shouldReceive('guard')->with(null)->andReturn(fakeGuard(1));

    expect((new AuthUserStrategy($factory))->resolve())->toBe(1);
});

it('AuthUserStrategy has name "auth-user"', function () {
    $factory = Mockery::mock(AuthFactory::class);

    expect((new AuthUserStrategy($factory))->name())->toBe('auth-user');
});

// --- SessionOnlyStrategy -----------------------------------------------------

it('SessionOnlyStrategy returns the session id', function () {
    $session = Mockery::mock(Session::class);
    $session->shouldReceive('getId')->andReturn('sess_abc123');

    expect((new SessionOnlyStrategy($session))->resolve())->toBe('sess_abc123');
});

it('SessionOnlyStrategy returns null for empty session id', function () {
    $session = Mockery::mock(Session::class);
    $session->shouldReceive('getId')->andReturn('');

    expect((new SessionOnlyStrategy($session))->resolve())->toBeNull();
});

it('SessionOnlyStrategy swallows exceptions thrown by the session', function () {
    $session = Mockery::mock(Session::class);
    $session->shouldReceive('getId')->andThrow(new RuntimeException('session blew up'));

    expect((new SessionOnlyStrategy($session))->resolve())->toBeNull();
});

it('SessionOnlyStrategy has name "session"', function () {
    $session = Mockery::mock(Session::class);

    expect((new SessionOnlyStrategy($session))->name())->toBe('session');
});

// --- TenantStrategy ----------------------------------------------------------

it('TenantStrategy returns string from resolver', function () {
    expect((new TenantStrategy(fn () => 'tenant-1'))->resolve())->toBe('tenant-1');
});

it('TenantStrategy returns int from resolver', function () {
    expect((new TenantStrategy(fn () => 99))->resolve())->toBe(99);
});

it('TenantStrategy returns null when resolver returns null', function () {
    expect((new TenantStrategy(fn () => null))->resolve())->toBeNull();
});

it('TenantStrategy returns null for empty string', function () {
    expect((new TenantStrategy(fn () => ''))->resolve())->toBeNull();
});

it('TenantStrategy returns null for unsupported types', function () {
    expect((new TenantStrategy(fn () => new stdClass()))->resolve())->toBeNull();
});

it('TenantStrategy swallows resolver exceptions', function () {
    $strategy = new TenantStrategy(fn () => throw new RuntimeException('no tenant'));

    expect($strategy->resolve())->toBeNull();
});

it('TenantStrategy has name "tenant"', function () {
    expect((new TenantStrategy(fn () => null))->name())->toBe('tenant');
});

// --- CallbackStrategy --------------------------------------------------------

it('CallbackStrategy returns the resolver value', function () {
    expect((new CallbackStrategy(fn () => 'x'))->resolve())->toBe('x');
});

it('CallbackStrategy returns int from resolver', function () {
    expect((new CallbackStrategy(fn () => 7))->resolve())->toBe(7);
});

it('CallbackStrategy returns null on empty string', function () {
    expect((new CallbackStrategy(fn () => ''))->resolve())->toBeNull();
});

it('CallbackStrategy returns null on unsupported types', function () {
    expect((new CallbackStrategy(fn () => 1.5))->resolve())->toBeNull();
});

it('CallbackStrategy swallows resolver exceptions', function () {
    $strategy = new CallbackStrategy(fn () => throw new RuntimeException('boom'));

    expect($strategy->resolve())->toBeNull();
});

it('CallbackStrategy uses default name', function () {
    expect((new CallbackStrategy(fn () => null))->name())->toBe('callback');
});

it('CallbackStrategy uses custom name', function () {
    expect((new CallbackStrategy(fn () => null, 'project'))->name())->toBe('project');
});

// --- CompositeStrategy -------------------------------------------------------

it('CompositeStrategy returns first non-null resolution', function () {
    $strategy = new CompositeStrategy(
        new CallbackStrategy(fn () => null, 'first'),
        new CallbackStrategy(fn () => 'second-value', 'second'),
        new CallbackStrategy(fn () => 'third-value', 'third'),
    );

    expect($strategy->resolve())->toBe('second-value');
});

it('CompositeStrategy returns null when all children return null', function () {
    $strategy = new CompositeStrategy(
        new CallbackStrategy(fn () => null, 'a'),
        new CallbackStrategy(fn () => null, 'b'),
    );

    expect($strategy->resolve())->toBeNull();
});

it('CompositeStrategy with no strategies returns null and has empty brackets', function () {
    $empty = new CompositeStrategy();

    expect($empty->resolve())->toBeNull()
        ->and($empty->name())->toBe('composite[]');
});

it('CompositeStrategy name lists child strategy names', function () {
    $strategy = new CompositeStrategy(
        new CallbackStrategy(fn () => null, 'auth-user'),
        new CallbackStrategy(fn () => null, 'fallback'),
    );

    expect($strategy->name())->toBe('composite[auth-user,fallback]');
});

it('CompositeStrategy short-circuits on first match', function () {
    $secondCalled = false;
    $strategy = new CompositeStrategy(
        new CallbackStrategy(fn () => 'hit'),
        new CallbackStrategy(function () use (&$secondCalled) {
            $secondCalled = true;

            return 'should-not-run';
        }),
    );

    $strategy->resolve();

    expect($secondCalled)->toBeFalse();
});
