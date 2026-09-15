<?php

namespace App\Http\Middleware;

use App\Services\TenantDatabaseService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureTenantSessionMatchesHost
{
    public function __construct(private TenantDatabaseService $tenantDb) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.single_db_per_app') || $this->tenantDb->isMainDomain($request)) {
            return $next($request);
        }

        $subdomain = $this->tenantDb->extractSubdomain($request);

        if ($subdomain === null) {
            return $next($request);
        }

        $sessionSubdomain = $request->session()->get('tenant_subdomain');

        if ($sessionSubdomain !== null && $sessionSubdomain !== $subdomain) {
            Log::warning('Tenant session used on a different tenant host.', [
                'user_id' => $request->user()?->getAuthIdentifier(),
                'session_tenant' => $sessionSubdomain,
                'request_tenant' => $subdomain,
            ]);

            abort(Response::HTTP_FORBIDDEN, 'Unauthorized access to this tenant.');
        }

        $request->session()->put('tenant_subdomain', $subdomain);

        return $next($request);
    }
}
