<?php

namespace Tests\Feature;

use App\Mail\RegisteredVerificationMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_user_can_still_use_app_while_verification_is_disabled(): void
    {
        config(['auth.email_verification_enabled' => false]);

        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $this->get('/')->assertOk();
    }

    public function test_unverified_user_is_redirected_to_notice_when_verification_is_enabled(): void
    {
        config(['auth.email_verification_enabled' => true]);

        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $this->get('/')->assertRedirect(route('verification.notice'));
    }

    public function test_verified_user_can_use_app_when_verification_is_enabled(): void
    {
        config(['auth.email_verification_enabled' => true]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get('/')->assertOk();
    }

    public function test_notice_page_renders_for_unverified_user(): void
    {
        config(['auth.email_verification_enabled' => true]);

        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $this->get(route('verification.notice'))
            ->assertOk()
            ->assertSee($user->email);
    }

    public function test_signed_link_marks_the_email_as_verified(): void
    {
        config(['auth.email_verification_enabled' => true]);

        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->get($url)->assertRedirect(route('dashboard'));

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_tampered_link_does_not_verify_the_email(): void
    {
        config(['auth.email_verification_enabled' => true]);

        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1('someone-else@example.test'),
        ]);

        $this->get($url)->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_resend_endpoint_sends_a_verification_mail(): void
    {
        config(['auth.email_verification_enabled' => true]);
        Mail::fake();

        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $this->post(route('verification.send'))->assertRedirect();

        Mail::assertSent(RegisteredVerificationMail::class, fn ($mail) => $mail->user->is($user));
    }

    public function test_registration_sends_verification_mail_when_enabled_with_a_real_mailer(): void
    {
        config([
            'auth.email_verification_enabled' => true,
            'mail.default' => 'smtp',
        ]);
        Mail::fake();

        $this->post('/register', [
            'name' => 'Pengguna Baru',
            'email' => 'baru@gawetracker.test',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertRedirect(route('dashboard'));

        Mail::assertSent(RegisteredVerificationMail::class);
    }

    public function test_registration_does_not_send_mail_with_the_log_mailer(): void
    {
        config([
            'auth.email_verification_enabled' => true,
            'mail.default' => 'log',
        ]);
        Mail::fake();

        $this->post('/register', [
            'name' => 'Pengguna Baru',
            'email' => 'baru@gawetracker.test',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertRedirect(route('dashboard'));

        Mail::assertNothingSent();
    }

    public function test_mark_verified_command_backfills_an_existing_account(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'lama@gawetracker.test']);

        $this->artisan('email:mark-verified', ['email' => 'lama@gawetracker.test'])
            ->assertExitCode(0);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_mark_verified_command_fails_for_unknown_account(): void
    {
        $this->artisan('email:mark-verified', ['email' => 'tidak@ada.test'])
            ->assertExitCode(1);
    }
}
