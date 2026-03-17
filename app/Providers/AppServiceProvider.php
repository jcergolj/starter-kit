<?php

namespace App\Providers;

use App\Enums\RoleEnum;
use App\Services\TenantDatabaseService;
use HotwiredLaravel\TurboLaravel\Http\PendingTurboStreamResponse;
use HotwiredLaravel\TurboLaravel\Http\TurboResponseFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Opcodes\LogViewer\Facades\LogViewer;
use URL;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('TenantDatabaseService', fn ($app) => new TenantDatabaseService);
    }

    public function boot(): void
    {
        LogViewer::auth(fn ($request) => $request->user()?->role === RoleEnum::Superadmin);

        DB::prohibitDestructiveCommands($this->app->isProduction());

        Model::unguard();

        Model::preventSilentlyDiscardingAttributes();

        Model::preventLazyLoading(! $this->app->isProduction());

        Model::preventAccessingMissingAttributes();

        if (! $this->app->isLocal() && ! $this->app->environment('testing')) {
            URL::forceScheme('https');
        }

        $this->loadViewsFrom(resource_path('turbo'), 'turbo');

        PendingTurboStreamResponse::macro('reload', fn () => TurboResponseFactory::makeStream('<turbo-stream action="refresh"></turbo-stream>')
        );
    }
}
