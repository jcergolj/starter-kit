<?php

declare(strict_types=1);

use App\Features\UserManagement\Controllers\BlockedUserController;
use App\Features\UserManagement\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->group(function (): void {
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::post('users/{user}/block', [BlockedUserController::class, 'store'])->name('blocked-users.store');
    Route::delete('users/{user}/block', [BlockedUserController::class, 'destroy'])->name('blocked-users.destroy');
});
