<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Jcergolj\InAppNotifications\Facades\InAppNotification;

class PasswordController extends Controller
{
    public function edit()
    {
        return view('settings.password.edit');
    }

    public function update(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update([
            'password' => $request->input('password'),
        ]);

        InAppNotification::success(__('Password updated.'));

        return back();
    }
}
