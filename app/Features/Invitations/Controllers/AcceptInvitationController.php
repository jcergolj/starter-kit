<?php

declare(strict_types=1);

namespace App\Features\Invitations\Controllers;

use App\DataTransferObjects\UserSettings;
use App\Features\Invitations\Requests\AcceptInvitationRequest;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
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

        return view('invitations::invitations.accept', ['invitation' => $invitation]);
    }

    public function store(AcceptInvitationRequest $request, string $token): RedirectResponse
    {
        $invitation = Invitation::where('token', $token)->firstOrFail();

        try {
            $accepted = DB::transaction(function () use ($request, $token): bool {
                $claimed = Invitation::where('token', $token)
                    ->whereNull('accepted_at')
                    ->where('expires_at', '>', now())
                    ->update(['accepted_at' => now()]);

                if ($claimed !== 1) {
                    return false;
                }

                $invitation = Invitation::where('token', $token)->firstOrFail();

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
        } catch (UniqueConstraintViolationException $exception) {
            throw_unless(User::where('email', $invitation->email)->exists(), $exception);

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
