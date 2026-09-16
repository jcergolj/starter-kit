<?php

namespace App\Features\Settings\Controllers;

use App\Features\Settings\Requests\UpdatePasswordRequest;
use App\Http\Controllers\Controller;
use Jcergolj\InAppNotifications\Facades\InAppNotification;

class PasswordController extends Controller
{
    public function edit()
    {
        return view('settings::settings.password.edit');
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
