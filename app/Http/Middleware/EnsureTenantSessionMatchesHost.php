<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureTenantSessionMatchesHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.single_db_per_app')) {
            return $next($request);
        }

        $host = $request->getHost();

        $sessionHost = $request->session()->get('tenant_host');

        if (($sessionHost !== null && $sessionHost !== $host)
            || ($sessionHost === null && $request->session()->hasAny([Auth::guard('web')->getName(), 'login.id']))) {
            Log::warning('Session used outside its database host.', [
                'session_host' => $sessionHost,
                'request_host' => $host,
            ]);

            abort(Response::HTTP_FORBIDDEN, 'Unauthorized access to this tenant.');
        }

        $request->session()->put('tenant_host', $host);

        return $next($request);
    }
}
