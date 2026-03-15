<?php

use App\Http\Controllers\AcceptInvitationController;
use App\Http\Controllers\BlockedUserController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\Settings\ConfirmedTwoFactorController;
use App\Http\Controllers\Settings\LanguageController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\RecoveryCodesController;
use App\Http\Controllers\Settings\TwoFactorController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::view('/', 'welcome')->name('home');

// Public — invite acceptance (no auth)
Route::get('invite/{token}', [AcceptInvitationController::class, 'show'])
    ->name('invitations.accept');
Route::post('invite/{token}', [AcceptInvitationController::class, 'store'])
    ->name('accept.invitations.store');

// Admin — send and revoke invitations
Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('invitations/create', [InvitationController::class, 'create'])
        ->name('invitations.create');
    Route::post('invitations', [InvitationController::class, 'store'])
        ->name('invitations.store');
    Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])
        ->name('invitations.destroy');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::post('users/{user}/block', [BlockedUserController::class, 'store'])->name('blocked-users.store');
    Route::delete('users/{user}/block', [BlockedUserController::class, 'destroy'])->name('blocked-users.destroy');
});

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('settings', [SettingsController::class, 'show'])->name('settings');

    Route::prefix('settings')->as('settings.')->group(function () {
        Route::singleton('profile', ProfileController::class)->only(['edit', 'update']);
        Route::get('profile/delete', [ProfileController::class, 'delete'])->name('profile.delete');
        Route::post('profile/delete', [ProfileController::class, 'destroy'])->name('profile.destroy');
        Route::singleton('password', PasswordController::class)->only(['edit', 'update']);
        Route::singleton('language', LanguageController::class)->only(['edit', 'update']);

        if (Features::canManageTwoFactorAuthentication()) {
            Route::middleware(when(Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'), ['password.confirm'], []))->group(function () {
                Route::singleton('two-factor', TwoFactorController::class)->destroyable()->only(['edit', 'update', 'destroy']);
                Route::singleton('confirmed-two-factor', ConfirmedTwoFactorController::class)->only(['edit', 'update']);
                Route::singleton('recovery-codes', RecoveryCodesController::class)->only(['edit', 'update']);
            });
        }
    });
});
