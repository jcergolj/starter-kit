<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsNotBlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('logout')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user && $user->isBlocked()) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
