<?php

declare(strict_types=1);

namespace Vented\Plenum\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Vented\Plenum\Facades\Plenum;

final class Authorize
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Plenum::check($request)) {
            abort(403);
        }

        return $next($request);
    }
}
