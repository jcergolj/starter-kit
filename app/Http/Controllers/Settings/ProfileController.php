<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\DeleteProfileRequest;
use App\Http\Requests\Settings\UpdateProfileRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Jcergolj\InAppNotifications\Facades\InAppNotification;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('settings.profile.edit', [
            'name' => $request->user()->name,
            'email' => $request->user()->email,
        ]);
    }

    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();

        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        InAppNotification::success(__('Your profile has been updated.'));

        return back();
    }

    public function delete()
    {
        return view('settings.profile.delete');
    }

    public function destroy(DeleteProfileRequest $request)
    {
        $user = $request->user();

        Auth::guard('web')->logout();

        $user->delete();

        Session::invalidate();
        Session::regenerateToken();

        return redirect('/');
    }
}
