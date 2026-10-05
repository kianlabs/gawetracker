<?php

namespace App\Models;

use App\Mail\RegisteredVerificationMail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;

#[Fillable(['name', 'email', 'password', 'weekly_target'])]
#[Hidden(['password', 'remember_token', 'gmail_access_token', 'gmail_refresh_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * Tokens are encrypted at rest; the refresh token especially must never be
     * readable from a DB dump.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'gmail_access_token' => 'encrypted',
            'gmail_refresh_token' => 'encrypted',
            'gmail_token_expires_at' => 'datetime',
            'gmail_connected_at' => 'datetime',
            'weekly_target' => 'integer',
        ];
    }

    /**
     * Whether this user has linked a Gmail mailbox for automatic ingestion.
     */
    public function hasGmailConnected(): bool
    {
        return ! empty($this->gmail_refresh_token);
    }

    /**
     * The weekly application target, falling back to the PRD default (8) when
     * an older row predates the column or holds a nonsensical value.
     */
    public function weeklyTarget(): int
    {
        return $this->weekly_target > 0 ? (int) $this->weekly_target : 8;
    }

    /**
     * Postings discovered on this user's behalf.
     */
    public function jobPostings(): HasMany
    {
        return $this->hasMany(JobPosting::class);
    }

    /**
     * Send our Indonesian verification mail instead of the framework default.
     *
     * Overriding this keeps both entry points — the register controller and the
     * "resend" button — on the same branded, localised template.
     */
    public function sendEmailVerificationNotification(): void
    {
        Mail::to($this->email)->send(new RegisteredVerificationMail($this));
    }
}
