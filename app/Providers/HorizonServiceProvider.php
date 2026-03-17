<?php

namespace App\Providers;

use App\Enums\RoleEnum;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        Horizon::routeMailNotificationsTo(config('app.superadmin_email'));
    }

    protected function gate(): void
    {
        Gate::define('viewHorizon', fn ($user = null) => $user?->role === RoleEnum::Superadmin);
    }
}
