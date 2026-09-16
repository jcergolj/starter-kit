<?php

namespace App\Features\UserManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Jcergolj\InAppNotifications\Facades\InAppNotification;

class BlockedUserController extends Controller
{
    public function store(User $user): RedirectResponse
    {
        Gate::authorize('manage', $user);

        $user->update(['blocked_at' => now()]);

        InAppNotification::success(__('User blocked.'));

        return to_route('users.index');
    }

    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('manage', $user);

        $user->update(['blocked_at' => null]);

        InAppNotification::success(__('User unblocked.'));

        return to_route('users.index');
    }
}
