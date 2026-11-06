<?php

declare(strict_types=1);

namespace App\Features\Invitations\Controllers;

use App\Features\Invitations\Mail\InvitationMail;
use App\Features\Invitations\Requests\SendInvitationRequest;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Services\TenantDatabaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Jcergolj\InAppNotifications\Facades\InAppNotification;
use Throwable;

class InvitationController extends Controller
{
    public function __construct(private readonly TenantDatabaseService $tenantDatabase) {}

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

        $tenantSubdomain = config('app.single_db_per_app') || $this->tenantDatabase->isMainDomain($request)
            ? null
            : $this->tenantDatabase->extractSubdomain($request);

        try {
            Mail::to($invitation->email)->send(new InvitationMail($invitation, $tenantSubdomain));
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
