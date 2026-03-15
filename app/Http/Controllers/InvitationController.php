<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendInvitationRequest;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Jcergolj\InAppNotifications\Facades\InAppNotification;

class InvitationController extends Controller
{
    public function create(Request $request): View
    {
        $pendingInvitations = Invitation::pending()->get();

        return view('invitations.create', ['pendingInvitations' => $pendingInvitations]);
    }

    public function store(SendInvitationRequest $request): RedirectResponse
    {
        $invitation = Invitation::createFor($request->validated('email'));

        Mail::to($invitation->email)->send(new InvitationMail($invitation));

        InAppNotification::success(__('Invitation sent successfully.'));

        return to_route('invitations.create');
    }

    public function destroy(Invitation $invitation): RedirectResponse
    {
        if ($invitation->isPending()) {
            $invitation->delete();
        }

        InAppNotification::success(__('Invitation revoked.'));

        return to_route('invitations.create');
    }
}
