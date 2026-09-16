<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class FeatureServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::addNamespace('authentication', app_path('Features/Authentication/Views'));
        View::addNamespace('dashboard', app_path('Features/Dashboard/Views'));
        View::addNamespace('invitations', app_path('Features/Invitations/Views'));
        View::addNamespace('settings', app_path('Features/Settings/Views'));
        View::addNamespace('user-management', app_path('Features/UserManagement/Views'));
    }
}
