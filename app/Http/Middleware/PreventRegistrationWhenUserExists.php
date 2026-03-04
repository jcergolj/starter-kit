<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventRegistrationWhenUserExists
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.single_user_mode') && $request->routeIs('register', 'register.store') && User::exists()) {
            return to_route('login');
        }

        return $next($request);
    }
}
