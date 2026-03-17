<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\ModelServiceProvider;
use App\Providers\TranslationServiceProvider;
use App\Providers\TurboServiceProvider;
use Bugsnag\BugsnagLaravel\BugsnagServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    HorizonServiceProvider::class,
    BugsnagServiceProvider::class,
    ModelServiceProvider::class,
    TranslationServiceProvider::class,
    TurboServiceProvider::class,
];
