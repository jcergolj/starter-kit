<?php

use App\Features\Settings\Controllers\ConfirmedTwoFactorController;
use App\Features\Settings\Controllers\LanguageController;
use App\Features\Settings\Controllers\PasswordController;
use App\Features\Settings\Controllers\ProfileController;
use App\Features\Settings\Controllers\RecoveryCodesController;
use App\Features\Settings\Controllers\SettingsController;
use App\Features\Settings\Controllers\TwoFactorController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('settings', [SettingsController::class, 'show'])->name('settings');

    Route::prefix('settings')->as('settings.')->group(function (): void {
        Route::singleton('profile', ProfileController::class)->only(['edit', 'update']);
        Route::get('profile/delete', [ProfileController::class, 'delete'])->name('profile.delete');
        Route::post('profile/delete', [ProfileController::class, 'destroy'])->name('profile.destroy');
        Route::singleton('password', PasswordController::class)->only(['edit', 'update']);
        Route::singleton('language', LanguageController::class)->only(['edit', 'update']);

        if (Features::canManageTwoFactorAuthentication()) {
            Route::middleware(when(Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'), ['password.confirm'], []))->group(function (): void {
                Route::singleton('two-factor', TwoFactorController::class)->destroyable()->only(['edit', 'update', 'destroy']);
                Route::singleton('confirmed-two-factor', ConfirmedTwoFactorController::class)->only(['edit', 'update']);
                Route::singleton('recovery-codes', RecoveryCodesController::class)->only(['edit', 'update']);
            });
        }
    });
});
