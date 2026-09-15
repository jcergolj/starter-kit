<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Jcergolj\InAppNotifications\Facades\InAppNotification;
use Symfony\Component\HttpFoundation\Response;

class BlockedUserController extends Controller
{
    public function store(User $user): RedirectResponse
    {
        abort_if($user->isAdmin(), Response::HTTP_FORBIDDEN);

        $user->forceFill(['blocked_at' => now()])->save();

        InAppNotification::success(__('User blocked.'));

        return to_route('users.index');
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_if($user->isAdmin(), Response::HTTP_FORBIDDEN);

        $user->forceFill(['blocked_at' => null])->save();

        InAppNotification::success(__('User unblocked.'));

        return to_route('users.index');
    }
}
