<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Jcergolj\InAppNotifications\Facades\InAppNotification;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::where('role', RoleEnum::User)->get();

        return view('users.index', ['users' => $users]);
    }

    public function edit(User $user): View
    {
        abort_if($user->isAdmin(), Response::HTTP_FORBIDDEN);

        return view('users.edit', ['user' => $user]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        abort_if($user->isAdmin(), Response::HTTP_FORBIDDEN);

        $user->update($request->validated());

        InAppNotification::success(__('User updated.'));

        return to_route('users.index');
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_if($user->isAdmin(), Response::HTTP_FORBIDDEN);

        $user->delete();

        InAppNotification::success(__('User deleted.'));

        return to_route('users.index');
    }
}
