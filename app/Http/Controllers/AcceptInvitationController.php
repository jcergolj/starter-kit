<?php

namespace App\Http\Controllers;

use App\DataTransferObjects\UserSettings;
use App\Http\Requests\AcceptInvitationRequest;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AcceptInvitationController extends Controller
{
    public function show(string $token): View|RedirectResponse
    {
        $invitation = Invitation::where('token', $token)->firstOrFail();

        if (! $invitation->isPending()) {
            return $this->invalidInvitationResponse();
        }

        return view('invitations.accept', ['invitation' => $invitation]);
    }

    public function store(AcceptInvitationRequest $request, string $token): RedirectResponse
    {
        Invitation::where('token', $token)->firstOrFail();

        try {
            $accepted = DB::transaction(function () use ($request, $token): bool {
                $invitation = Invitation::query()
                    ->where('token', $token)
                    ->whereNull('accepted_at')
                    ->where('expires_at', '>', now())
                    ->first();

                if ($invitation === null) {
                    return false;
                }

                if (User::where('email', $invitation->email)->exists()) {
                    return false;
                }

                $claimed = Invitation::query()
                    ->whereKey($invitation->id)
                    ->whereNull('accepted_at')
                    ->where('expires_at', '>', now())
                    ->update(['accepted_at' => now()]);

                if ($claimed !== 1) {
                    return false;
                }

                User::create([
                    'name' => $request->validated('name'),
                    'username' => $request->validated('username'),
                    'password' => $request->validated('password'),
                    'email' => $invitation->email,
                    'role' => $invitation->role,
                    'email_verified_at' => now(),
                    'settings' => (new UserSettings($invitation->lang))->toArray(),
                ]);

                return true;
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }

            return $this->invalidInvitationResponse();
        }

        if (! $accepted) {
            return $this->invalidInvitationResponse();
        }

        return to_route('login')->with('status', __('Invitation accepted. You can now log in.'));
    }

    private function invalidInvitationResponse(): RedirectResponse
    {
        return to_route('login')->with('status', __('This invitation is no longer valid.'));
    }
}
