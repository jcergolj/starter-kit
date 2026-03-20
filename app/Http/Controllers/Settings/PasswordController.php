<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdatePasswordRequest;
use Jcergolj\InAppNotifications\Facades\InAppNotification;

class PasswordController extends Controller
{
    public function edit()
    {
        return view('settings.password.edit');
    }

    public function update(UpdatePasswordRequest $request)
    {
        $request->user()->update([
            'password' => $request->input('password'),
        ]);

        InAppNotification::success(__('Password updated.'));

        return back();
    }
}
