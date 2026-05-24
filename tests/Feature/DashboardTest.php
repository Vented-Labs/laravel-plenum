<?php

declare(strict_types=1);

use Vented\Plenum\Contracts\HealthChecker;
use Vented\Plenum\Facades\Plenum;

it('registers the dashboard route by default in local env', function () {
    $this->bootApp('local');

    $this->get('/plenum')->assertOk();
});

it('does not register the dashboard route in non-local env by default', function () {
    $this->bootApp('production');

    $this->get('/plenum')->assertNotFound();
});

it('registers the dashboard in production when explicitly enabled', function () {
    $this->bootApp('production', ['plenum.dashboard.enabled' => true]);
    Plenum::auth(fn () => true);

    $this->get('/plenum')->assertOk();
});

it('respects an explicit enabled=false in local', function () {
    $this->bootApp('local', ['plenum.dashboard.enabled' => false]);

    $this->get('/plenum')->assertNotFound();
});

it('denies access in production without a custom gate', function () {
    $this->bootApp('production', ['plenum.dashboard.enabled' => true]);

    $this->get('/plenum')->assertForbidden();
});

it('grants access in production when Plenum::auth returns true', function () {
    $this->bootApp('production', ['plenum.dashboard.enabled' => true]);
    Plenum::auth(fn () => true);

    $this->get('/plenum')->assertOk();
});

it('passes the request to the auth callback', function () {
    $this->bootApp('production', ['plenum.dashboard.enabled' => true]);

    $received = null;
    Plenum::auth(function ($request) use (&$received) {
        $received = $request;

        return true;
    });

    $this->get('/plenum?token=abc')->assertOk();
    expect($received)->not->toBeNull()
        ->and($received->query('token'))->toBe('abc');
});

it('uses the configured path prefix', function () {
    $this->bootApp('local', ['plenum.dashboard.path' => 'admin/plenum']);

    $this->get('/admin/plenum')->assertOk();
    $this->get('/plenum')->assertNotFound();
});

it('renders strategy, driver names, node names and up/down badges', function () {
    $this->bootApp('local', [
        'plenum.drivers.database.nodes' => [
            'db_1' => ['host' => 'h1', 'port' => 5432],
            'db_2' => ['host' => 'h2', 'port' => 5432],
        ],
    ]);

    $this->app->make(HealthChecker::class)->markDown('database', 'db_2');

    $response = $this->get('/plenum');
    $response->assertOk();
    $body = $response->getContent();

    expect($body)->toContain('auth-user')
        ->and($body)->toContain('database')
        ->and($body)->toContain('db_1')
        ->and($body)->toContain('db_2')
        ->and($body)->toContain('data-status="up"')
        ->and($body)->toContain('data-status="down"');
});

it('embeds the bundled stylesheet inline', function () {
    $this->bootApp('local');

    $body = (string) $this->get('/plenum')->getContent();

    expect($body)->toContain('<style>');
});

it('renders the no-drivers empty state when nothing is configured', function () {
    $this->bootApp('local');

    $body = (string) $this->get('/plenum')->getContent();

    expect($body)->toContain('No drivers configured');
});

it('renders the distribution table with non-zero counts', function () {
    $this->bootApp('local', [
        'plenum.dashboard.distribution_samples' => 50,
        'plenum.drivers.database.nodes' => [
            'db_1' => ['host' => 'h1', 'port' => 5432],
            'db_2' => ['host' => 'h2', 'port' => 5432],
        ],
    ]);

    $body = (string) $this->get('/plenum')->getContent();

    expect($body)->toContain('Distribution')
        ->and($body)->toContain('synthetic keys');
});

it('skips the distribution table when samples is zero', function () {
    $this->bootApp('local', [
        'plenum.dashboard.distribution_samples' => 0,
        'plenum.drivers.database.nodes' => [
            'db_1' => ['host' => 'h1', 'port' => 5432],
        ],
    ]);

    $body = (string) $this->get('/plenum')->getContent();

    expect($body)->not->toContain('Distribution');
});

it('renders the page when every node is unhealthy (distribution counts are all zero)', function () {
    $this->bootApp('local', [
        'plenum.dashboard.distribution_samples' => 10,
        'plenum.drivers.database.nodes' => [
            'db_1' => ['host' => 'h1', 'port' => 5432],
            'db_2' => ['host' => 'h2', 'port' => 5432],
        ],
    ]);

    $health = $this->app->make(HealthChecker::class);
    $health->markDown('database', 'db_1');
    $health->markDown('database', 'db_2');

    $response = $this->get('/plenum');
    $response->assertOk();
    $body = (string) $response->getContent();

    expect($body)->toContain('data-status="down"')
        ->and(substr_count($body, 'width: 0%'))->toBeGreaterThan(0);
});

it('uses a custom domain when configured', function () {
    $this->bootApp('local', [
        'plenum.dashboard.domain' => 'admin.example.test',
        'plenum.dashboard.path' => '/',
    ]);

    $route = collect(app('router')->getRoutes()->getRoutes())
        ->first(fn ($r) => $r->getName() === 'plenum.dashboard');

    expect($route)->not->toBeNull()
        ->and($route->getDomain())->toBe('admin.example.test');
});
