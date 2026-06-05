<?php

declare(strict_types=1);

namespace App\Http\Controllers;

class SettingsController extends Controller
{
    public function show()
    {
        return view('settings.menu');
    }
}
