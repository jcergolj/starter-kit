<?php

namespace App\Providers;

use App\Enums\RoleEnum;
use App\Services\TenantDatabaseService;
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use HotwiredLaravel\TurboLaravel\Http\PendingTurboStreamResponse;
use HotwiredLaravel\TurboLaravel\Http\TurboResponseFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
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

        Lang::handleMissingKeysUsing(function (string $key, array $replacements, string $locale) {
            if (! App::environment(['production', 'staging'])) {
                return;
            }

            $skipMissingTranslationsFor = [
                'validation.custom.',
                'validation.values.',
                '(and :count more',
            ];

            if (Str::of($key)->startsWith($skipMissingTranslationsFor)) {
                return;
            }

            Bugsnag::notifyError('missing-translation', __('Missing translation key [:key], [:locale] detected.', ['key' => $key, 'locale' => $locale]));

            return $key;
        });

        $this->loadViewsFrom(resource_path('turbo'), 'turbo');

        PendingTurboStreamResponse::macro('reload', fn () => TurboResponseFactory::makeStream('<turbo-stream action="refresh"></turbo-stream>')
        );
    }
}
