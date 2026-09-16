<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::view('dashboard', 'dashboard::dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');
