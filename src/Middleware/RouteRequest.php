<?php

declare(strict_types=1);

namespace Vented\Plenum\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Vented\Plenum\Plenum;

/**
 * Drives one routing cycle per HTTP request. Under long-lived workers
 * (Octane / FrankenPHP / RoadRunner) every routed connection lives only for
 * the request that activated it — entering this middleware deactivates the
 * previous request's pins before resolving new ones.
 *
 * Any route group that touches a Plenum-routed driver must therefore include
 * this middleware. Routes that bypass it inherit whatever connection the most
 * recent routed request left in place on the worker.
 */
final class RouteRequest
{
    public function __construct(
        private readonly Plenum $plenum,
        private readonly ConfigRepository $config,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $routed = $this->plenum->routeCurrentRequest();

        /** @var Response $response */
        $response = $next($request);

        if ($this->config->get('plenum.expose_debug_header', false)) {
            foreach ($routed as $driver => $node) {
                $response->headers->set("X-Plenum-{$driver}", $node);
            }
            $response->headers->set('X-Plenum-Strategy', $this->plenum->strategy()->name());
        }

        return $response;
    }
}
