<?php

namespace App\Features\Invitations\Controllers;

use App\Features\Invitations\Mail\InvitationMail;
use App\Features\Invitations\Requests\SendInvitationRequest;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Jcergolj\InAppNotifications\Facades\InAppNotification;
use Throwable;

class InvitationController extends Controller
{
    public function create(Request $request): View
    {
        $pendingInvitations = Invitation::pending()
            ->orderBy('id')
            ->paginate(15);

        return view('invitations::invitations.create', ['pendingInvitations' => $pendingInvitations]);
    }

    public function store(SendInvitationRequest $request): RedirectResponse
    {
        $invitation = Invitation::createFor($request->validated('email'));

        try {
            Mail::to($invitation->email)->send(new InvitationMail($invitation));
        } catch (Throwable $exception) {
            report($exception);
            $invitation->delete();

            InAppNotification::error(__('Invitation could not be sent. Please try again.'));

            return to_route('invitations.create');
        }

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
