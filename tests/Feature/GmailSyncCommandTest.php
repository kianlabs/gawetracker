<?php

namespace Tests\Feature;

use App\Models\IngestedEmail;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The scheduled `emails:import --gmail` run.
 *
 * Both the OAuth token endpoint and the Gmail API are faked. The important
 * behaviour under test is per-user scoping: two connected mailboxes each end up
 * with their own ledger rows and applications, even though the messages are
 * identical.
 */
class GmailSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.gmail.client_id', 'test-client-id');
        config()->set('services.gmail.client_secret', 'test-client-secret');
        config()->set('services.gmail.redirect_uri', 'https://gawetracker.test/gmail/callback');
    }

    /**
     * Create a user with a usable (non-expired) Gmail connection.
     */
    private function connectedUser(string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->forceFill([
            'gmail_access_token' => 'ya29.token',
            'gmail_refresh_token' => 'refresh-'.$email,
            'gmail_token_expires_at' => now()->addHour(),
            'gmail_email' => $email,
            'gmail_connected_at' => now(),
        ])->save();

        return $user;
    }

    /**
     * A Gmail API message resource carrying a job email.
     */
    private function message(string $id, string $subject, string $body): array
    {
        $b64 = rtrim(strtr(base64_encode($body), '+/', '-_'), '=');

        return [
            'id' => $id,
            'payload' => [
                'mimeType' => 'multipart/alternative',
                'headers' => [
                    ['name' => 'From', 'value' => 'Jobstreet <noreply@id.jobstreet.com>'],
                    ['name' => 'Subject', 'value' => $subject],
                    ['name' => 'Date', 'value' => 'Mon, 29 Sep 2026 09:15:00 +0700'],
                ],
                'parts' => [
                    ['mimeType' => 'text/plain', 'body' => ['data' => $b64]],
                ],
            ],
        ];
    }

    /**
     * Fake the Gmail API: a two-message mailbox (an "applied" mail and a later
     * "interview" invite for the same role).
     */
    private function fakeGmail(): void
    {
        $applied = $this->message(
            'gmail-msg-1',
            'Your application for Backend Engineer at PT Teknologi Maju',
            'Thank you for applying for the position of Backend Engineer at PT Teknologi Maju. We have received your application.',
        );

        $interview = $this->message(
            'gmail-msg-2',
            'Interview invitation for Backend Engineer at PT Teknologi Maju',
            'We would like to invite you to a job interview for the Backend Engineer position at PT Teknologi Maju.',
        );

        Http::fake(function ($request) use ($applied, $interview) {
            $url = $request->url();

            if (str_contains($url, '/messages/gmail-msg-1')) {
                return Http::response($applied);
            }
            if (str_contains($url, '/messages/gmail-msg-2')) {
                return Http::response($interview);
            }
            if (str_contains($url, '/messages')) {
                return Http::response(['messages' => [['id' => 'gmail-msg-1'], ['id' => 'gmail-msg-2']]]);
            }

            return Http::response([], 404);
        });
    }

    public function test_it_scopes_each_users_ingested_mail_to_that_user(): void
    {
        $this->fakeGmail();

        $alice = $this->connectedUser('alice@example.com');
        $bob = $this->connectedUser('bob@example.com');
        // Not connected — must be skipped entirely.
        $carol = User::factory()->create(['email' => 'carol@example.com']);

        $this->artisan('emails:import --gmail')->assertExitCode(0);

        // The command leaves the last owner signed in; clear it so the
        // assertions below see every user's rows, not just that one.
        $this->app['auth']->logout();

        // Both connected users ingest the same two messages, but each gets
        // their own ledger rows and their own application.
        $this->assertSame(2, IngestedEmail::where('user_id', $alice->id)->count());
        $this->assertSame(2, IngestedEmail::where('user_id', $bob->id)->count());
        $this->assertSame(0, IngestedEmail::where('user_id', $carol->id)->count());

        foreach ([$alice, $bob] as $user) {
            $this->assertSame(1, JobApplication::where('user_id', $user->id)->count());
            $app = JobApplication::where('user_id', $user->id)->first();
            $this->assertSame('PT Teknologi Maju', $app->company);
            // The interview invite matched and advanced the same application.
            $this->assertSame('interview', $app->status);
        }

        $this->assertSame(0, JobApplication::where('user_id', $carol->id)->count());
    }

    public function test_a_second_run_is_idempotent(): void
    {
        $this->fakeGmail();
        $alice = $this->connectedUser('alice@example.com');

        $this->artisan('emails:import --gmail')->assertExitCode(0);
        $this->artisan('emails:import --gmail')->assertExitCode(0);

        $this->app['auth']->logout();

        $this->assertSame(2, IngestedEmail::where('user_id', $alice->id)->count());
        $this->assertSame(1, JobApplication::where('user_id', $alice->id)->count());
    }

    public function test_the_user_option_limits_the_sync_to_one_account(): void
    {
        $this->fakeGmail();
        $alice = $this->connectedUser('alice@example.com');
        $bob = $this->connectedUser('bob@example.com');

        $this->artisan('emails:import --gmail --user=alice@example.com')->assertExitCode(0);

        $this->app['auth']->logout();

        $this->assertSame(2, IngestedEmail::where('user_id', $alice->id)->count());
        $this->assertSame(0, IngestedEmail::where('user_id', $bob->id)->count());
    }

    public function test_it_exits_cleanly_when_nobody_is_connected(): void
    {
        Http::fake();
        User::factory()->create();

        $this->artisan('emails:import --gmail')->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_a_dead_token_is_skipped_without_aborting_the_run(): void
    {
        $broken = $this->connectedUser('broken@example.com');
        // Force a refresh that Google will reject.
        $broken->forceFill([
            'gmail_access_token' => 'stale',
            'gmail_token_expires_at' => now()->subMinute(),
        ])->save();

        $healthy = $this->connectedUser('healthy@example.com');

        $applied = $this->message(
            'gmail-msg-1',
            'Your application for Backend Engineer at PT Teknologi Maju',
            'Thank you for applying for the position of Backend Engineer at PT Teknologi Maju.',
        );

        Http::fake(function ($request) use ($applied) {
            if (str_contains($request->url(), 'oauth2.googleapis.com/token')) {
                return Http::response(['error' => 'invalid_grant'], 400);
            }
            if (str_contains($request->url(), '/messages/gmail-msg-1')) {
                return Http::response($applied);
            }
            if (str_contains($request->url(), '/messages')) {
                return Http::response(['messages' => [['id' => 'gmail-msg-1']]]);
            }

            return Http::response([], 404);
        });

        $this->artisan('emails:import --gmail')->assertExitCode(0);

        $this->app['auth']->logout();

        // The broken account contributed nothing; the healthy one still synced.
        $this->assertSame(0, IngestedEmail::where('user_id', $broken->id)->count());
        $this->assertSame(1, IngestedEmail::where('user_id', $healthy->id)->count());
    }
}
