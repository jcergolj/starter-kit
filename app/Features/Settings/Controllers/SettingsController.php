<?php

declare(strict_types=1);

namespace App\Features\Settings\Controllers;

use App\Http\Controllers\Controller;

class SettingsController extends Controller
{
    public function show()
    {
        return view('settings::settings.menu');
    }
}
