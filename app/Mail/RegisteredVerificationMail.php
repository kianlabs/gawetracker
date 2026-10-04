<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

/**
 * The verification email sent right after registration.
 *
 * Uses the signed `verification.verify` URL, so clicking the link both proves
 * the address is reachable and authorises the request without a login step.
 */
class RegisteredVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verifikasi email GaweTracker',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verify-email',
            with: ['url' => $this->verificationUrl()],
        );
    }

    /**
     * Signed, expiring link back to the app. Lifetime follows Laravel's own
     * auth.verification.expire (60 minutes by default).
     */
    protected function verificationUrl(): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $this->user->getKey(),
                'hash' => sha1($this->user->getEmailForVerification()),
            ],
        );
    }
}
