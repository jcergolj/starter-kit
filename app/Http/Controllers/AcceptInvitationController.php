<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptInvitationRequest;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AcceptInvitationController extends Controller
{
    public function show(string $token): View|RedirectResponse
    {
        $invitation = Invitation::where('token', $token)->firstOrFail();

        if (! $invitation->isPending()) {
            return to_route('login')->with('status', __('This invitation is no longer valid.'));
        }

        return view('invitations.accept', ['invitation' => $invitation]);
    }

    public function store(AcceptInvitationRequest $request, string $token): RedirectResponse
    {
        $invitation = Invitation::where('token', $token)->firstOrFail();

        if (! $invitation->isPending()) {
            return to_route('login')->with('status', __('This invitation is no longer valid.'));
        }

        User::create([
            'name' => $request->validated('name'),
            'username' => $request->validated('username'),
            'password' => $request->validated('password'),
            'email' => $invitation->email,
            'role' => $invitation->role,
            'email_verified_at' => now(),
        ]);

        $invitation->accept();

        return to_route('login')->with('status', __('Invitation accepted. You can now log in.'));
    }
}
