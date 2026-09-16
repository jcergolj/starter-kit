<?php

declare(strict_types=1);

use App\Features\Authentication\Controllers\CsrfTokenController;
use Illuminate\Support\Facades\Route;

Route::get('csrf-token', [CsrfTokenController::class, 'show'])
    ->middleware(['auth'])
    ->name('csrf-token');
