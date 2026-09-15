<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Jcergolj\InAppNotifications\Facades\InAppNotification;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;

class RecoveryCodesController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        if (! $request->user()->hasEnabledTwoFactorAuthentication()) {
            return to_route('settings.two-factor.edit');
        }

        return view('settings.recovery-codes.edit', [
            'user' => $user = $request->user(),
            'recoveryCodes' => json_decode((string) decrypt($user->two_factor_recovery_codes), true),
        ]);
    }

    public function update(Request $request, GenerateNewRecoveryCodes $generateRecoveryCodes): RedirectResponse
    {
        if (! $request->user()->hasEnabledTwoFactorAuthentication()) {
            return to_route('settings.two-factor.edit');
        }

        $generateRecoveryCodes($request->user());

        InAppNotification::success(__('New recovery codes generated.'));

        return to_route('settings.recovery-codes.edit');
    }
}
