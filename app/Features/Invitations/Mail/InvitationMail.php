<?php

namespace App\Features\Invitations\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Uri;

class InvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Invitation $invitation,
        public readonly ?string $tenantSubdomain = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [$this->invitation->email],
            subject: __('You have been invited'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'invitations::emails.invitation',
            with: [
                'acceptUrl' => $this->acceptanceUrl(),
                'expiresAt' => $this->invitation->expires_at,
            ],
        );
    }

    private function acceptanceUrl(): string
    {
        $path = route('invitations.accept', $this->invitation->token, false);
        $url = Uri::of(config('app.url'))->withPath($path);

        if ($this->tenantSubdomain !== null) {
            $url = $url->withHost($this->tenantSubdomain.'.'.config('app.domain'));
        }

        return (string) $url;
    }
}
