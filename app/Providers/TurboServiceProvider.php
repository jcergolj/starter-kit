<?php

namespace App\Providers;

use HotwiredLaravel\TurboLaravel\Http\PendingTurboStreamResponse;
use HotwiredLaravel\TurboLaravel\Http\TurboResponseFactory;
use Illuminate\Support\ServiceProvider;

class TurboServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(resource_path('turbo'), 'turbo');

        PendingTurboStreamResponse::macro('reload', function () {
            return TurboResponseFactory::makeStream('<turbo-stream action="refresh"></turbo-stream>');
        });
    }
}
