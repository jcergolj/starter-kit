<?php

declare(strict_types=1);

use App\Http\Middleware\ConnectToUserDatabase;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsNotBlocked;
use App\Http\Middleware\SetLocaleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustHosts(
            at: static function (): array {
                $domain = preg_quote((string) config('app.domain'), '/');

                return [
                    "^{$domain}$",
                    "^[a-z0-9_-]+\\.{$domain}$",
                ];
            },
            subdomains: false,
        );
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);
        $middleware->web(prepend: [
            ConnectToUserDatabase::class,
        ]);
        $middleware->web(append: [
            SetLocaleMiddleware::class,
            EnsureUserIsNotBlocked::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontTruncateRequestExceptions();
    })->create();
