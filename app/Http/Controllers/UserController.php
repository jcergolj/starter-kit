<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Jcergolj\InAppNotifications\Facades\InAppNotification;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::where('role', RoleEnum::User)
            ->orderBy('id')
            ->paginate(15);

        return view('users.index', ['users' => $users]);
    }

    public function edit(User $user): View
    {
        Gate::authorize('manage', $user);

        return view('users.edit', ['user' => $user]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('manage', $user);

        $attributes = $request->validated();
        $emailChanged = $attributes['email'] !== $user->email;

        if ($emailChanged) {
            $attributes['email_verified_at'] = null;
        }

        $user->update($attributes);

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        InAppNotification::success(__('User updated.'));

        return to_route('users.index');
    }

    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('manage', $user);

        $user->delete();

        InAppNotification::success(__('User deleted.'));

        return to_route('users.index');
    }
}
