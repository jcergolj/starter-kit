<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class ModelServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Model::unguard();

        Model::preventSilentlyDiscardingAttributes();

        Model::preventLazyLoading(! $this->app->isProduction());

        Model::preventAccessingMissingAttributes();
    }
}
