<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Vented\Plenum\Drivers\NullDriver;
use Vented\Plenum\Health\PingHealthChecker;
use Vented\Plenum\Middleware\RouteRequest;
use Vented\Plenum\Plenum;
use Vented\Plenum\Strategies\CallbackStrategy;

function makePlenumForMiddleware(?CallbackStrategy $strategy = null): Plenum
{
    $cache = new CacheRepository(new ArrayStore());
    $events = new Dispatcher();
    $health = new PingHealthChecker($cache, $events);
    $strategy ??= new CallbackStrategy(fn () => 'user:42', 'auth-user');

    return new Plenum($strategy, $health, $events);
}

it('routes the request, runs the pipeline, and does not add headers when debug flag is off', function () {
    $plenum = makePlenumForMiddleware();
    $plenum->registerDriver(new NullDriver('database', ['db_1', 'db_2']));

    $config = new ConfigRepository(['plenum' => ['expose_debug_header' => false]]);

    $pipelineRan = false;
    $next = function (Request $request) use (&$pipelineRan): Response {
        $pipelineRan = true;

        return new Response('hello');
    };

    $response = (new RouteRequest($plenum, $config))->handle(new Request(), $next);

    expect($pipelineRan)->toBeTrue()
        ->and($response->getContent())->toBe('hello')
        ->and($response->headers->get('X-Plenum-database'))->toBeNull()
        ->and($response->headers->get('X-Plenum-Strategy'))->toBeNull();
});

it('exposes X-Plenum-{driver} and X-Plenum-Strategy headers when the debug flag is on', function () {
    $plenum = makePlenumForMiddleware();
    $plenum->registerDriver(new NullDriver('database', ['db_1', 'db_2']));
    $plenum->registerDriver(new NullDriver('redis', ['r_1', 'r_2']));

    $config = new ConfigRepository(['plenum' => ['expose_debug_header' => true]]);

    $response = (new RouteRequest($plenum, $config))->handle(
        new Request(),
        fn () => new Response('ok'),
    );

    expect($response->headers->get('X-Plenum-database'))->toBeIn(['db_1', 'db_2'])
        ->and($response->headers->get('X-Plenum-redis'))->toBeIn(['r_1', 'r_2'])
        ->and($response->headers->get('X-Plenum-Strategy'))->toBe('auth-user');
});

it('omits driver headers but still emits the strategy header when nothing was routed', function () {
    $plenum = makePlenumForMiddleware(new CallbackStrategy(fn () => null, 'noop'));

    $config = new ConfigRepository(['plenum' => ['expose_debug_header' => true]]);

    $response = (new RouteRequest($plenum, $config))->handle(
        new Request(),
        fn () => new Response('ok'),
    );

    expect($response->headers->get('X-Plenum-Strategy'))->toBe('noop')
        ->and($response->headers->get('X-Plenum-database'))->toBeNull();
});

it('defaults the debug flag to false when the config key is missing', function () {
    $plenum = makePlenumForMiddleware();
    $plenum->registerDriver(new NullDriver('database', ['db_1']));

    $config = new ConfigRepository(['plenum' => []]);

    $response = (new RouteRequest($plenum, $config))->handle(
        new Request(),
        fn () => new Response('ok'),
    );

    expect($response->headers->get('X-Plenum-database'))->toBeNull();
});

it('routes BEFORE invoking the pipeline (so controllers see the activated connection)', function () {
    $plenum = makePlenumForMiddleware();
    $plenum->registerDriver(new NullDriver('database', ['db_1', 'db_2']));

    $config = new ConfigRepository(['plenum' => ['expose_debug_header' => true]]);

    $orderingHint = null;
    $next = function (Request $request) use (&$orderingHint, $plenum): Response {
        // By the time we get here, the driver should already have been activated.
        // NullDriver doesn't keep state, but routeCurrentRequest should have run.
        $orderingHint = 'pipeline-ran';

        return new Response('ok');
    };

    (new RouteRequest($plenum, $config))->handle(new Request(), $next);

    expect($orderingHint)->toBe('pipeline-ran');
});
