<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ConfirmTwoFactorRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Jcergolj\InAppNotifications\Facades\InAppNotification;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;

class ConfirmedTwoFactorController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        if ($request->user()->two_factor_secret === null) {
            return to_route('settings.two-factor.edit');
        }

        return view('settings.confirmed-two-factor.edit', [
            'user' => $user = $request->user(),
            'qrCodeSvg' => $user->twoFactorQrCodeSvg(),
            'setupCode' => decrypt($user->two_factor_secret),
        ]);
    }

    public function update(ConfirmTwoFactorRequest $request, ConfirmTwoFactorAuthentication $confirmTwoFactor): RedirectResponse
    {
        if ($request->user()->two_factor_secret === null) {
            return to_route('settings.two-factor.edit');
        }

        try {
            $confirmTwoFactor($request->user(), $request->validated('code'));
        } catch (ValidationException $e) {
            throw $e->errorBag('default');
        }

        InAppNotification::success(__('Two-factor confirmed.'));

        return to_route('settings.two-factor.edit');
    }
}
