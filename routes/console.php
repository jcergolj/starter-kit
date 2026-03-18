<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('backup:run')->everyTwoHours()->between('6:00', '23:00');
Schedule::command('backup:clean')->daily()->at('02:00');
Schedule::command('backup:monitor')->daily()->at('03:00');
