<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Jcergolj\InAppNotifications\Facades\InAppNotification;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;

class RecoveryCodesController extends Controller
{
    public function edit(Request $request)
    {
        return view('settings.recovery-codes.edit', [
            'user' => $user = $request->user(),
            'recoveryCodes' => json_decode((string) decrypt($user->two_factor_recovery_codes), true),
        ]);
    }

    public function update(Request $request, GenerateNewRecoveryCodes $generateRecoveryCodes)
    {
        $generateRecoveryCodes($request->user());

        InAppNotification::success(__('New recovery codes generated.'));

        return to_route('settings.recovery-codes.edit');
    }
}
