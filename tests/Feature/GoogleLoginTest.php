<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * "Sign in with Google": one flow that authenticates AND links the mailbox,
 * replacing the separate "Hubungkan Gmail" step.
 */
class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gmail.client_id' => 'test-client-id',
            'services.gmail.client_secret' => 'test-secret',
            'services.gmail.redirect_uri' => 'http://localhost/gmail/callback',
            'services.gmail.login_redirect_uri' => 'http://localhost/auth/google/callback',
            'services.gmail.allowed_emails' => null,
        ]);
    }

    /** Build a JWT id_token with the given claims (unsigned — Google sends it over TLS). */
    private function idToken(array $claims): string
    {
        $b64 = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

        return $b64(['alg' => 'RS256']).'.'.$b64($claims).'.signature';
    }

    private function fakeToken(array $claims, string $access = 'ya29.access', ?string $refresh = '1//refresh'): array
    {
        return [
            'oauth2.googleapis.com/token' => Http::response(array_filter([
                'access_token' => $access,
                'refresh_token' => $refresh,
                'expires_in' => 3600,
                'id_token' => $this->idToken($claims),
            ]), 200),
        ];
    }

    public function test_google_button_is_hidden_when_not_configured(): void
    {
        config(['services.gmail.client_id' => null, 'services.gmail.client_secret' => null]);

        $this->get('/login')
            ->assertOk()
            ->assertDontSee('Masuk dengan Google');
    }

    public function test_google_button_is_shown_when_configured(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Masuk dengan Google')
            ->assertSee(route('auth.google'));
    }

    public function test_redirect_sends_offline_consent_to_the_login_callback(): void
    {
        $response = $this->get(route('auth.google'));

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');

        $this->assertStringContainsString('accounts.google.com', $location);
        $this->assertStringContainsString('access_type=offline', $location);
        $this->assertStringContainsString('gmail.readonly', $location);
        // Must use the login callback, not the connect-Gmail one.
        $this->assertStringContainsString(
            'redirect_uri='.urlencode('http://localhost/auth/google/callback'),
            $location,
        );
        $this->assertStringContainsString('state=', $location);
    }

    public function test_first_sign_in_creates_the_user_and_links_the_mailbox(): void
    {
        Http::fake($this->fakeToken(['email' => 'ridzkyan0504@gmail.com', 'name' => 'Ridzky An']));

        $response = $this->withSession(['google_login_state' => 'state-abc'])
            ->get(route('auth.google.callback', ['code' => 'auth-code', 'state' => 'state-abc']));

        $response->assertRedirect(route('dashboard'));

        $user = User::where('email', 'ridzkyan0504@gmail.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Ridzky An', $user->name);
        $this->assertSame('ridzkyan0504@gmail.com', $user->gmail_email);
        $this->assertTrue($user->hasGmailConnected(), 'Signing in must also link the mailbox.');
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_existing_user_is_logged_in_and_mailbox_refreshed(): void
    {
        $user = User::factory()->create(['email' => 'kyan@gawetracker.test']);

        Http::fake($this->fakeToken(['email' => 'kyan@gawetracker.test', 'name' => 'Kyan']));

        $this->withSession(['google_login_state' => 'state-abc'])
            ->get(route('auth.google.callback', ['code' => 'auth-code', 'state' => 'state-abc']))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->fresh()->hasGmailConnected());
        // The account was not duplicated.
        $this->assertSame(1, User::where('email', 'kyan@gawetracker.test')->count());
    }

    public function test_sign_in_is_rejected_when_the_state_does_not_match(): void
    {
        Http::fake($this->fakeToken(['email' => 'attacker@gmail.com']));

        $this->withSession(['google_login_state' => 'expected'])
            ->get(route('auth.google.callback', ['code' => 'auth-code', 'state' => 'wrong']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_sign_in_is_rejected_when_consent_is_denied(): void
    {
        $this->get(route('auth.google.callback', ['error' => 'access_denied']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_allow_list_blocks_accounts_not_on_it(): void
    {
        config(['services.gmail.allowed_emails' => 'ridzkyan0504@gmail.com']);
        // An unrelated existing user makes the "first user" bootstrap unavailable.
        User::factory()->create(['email' => 'someone@else.test']);

        Http::fake($this->fakeToken(['email' => 'stranger@gmail.com']));

        $this->withSession(['google_login_state' => 'state-abc'])
            ->get(route('auth.google.callback', ['code' => 'auth-code', 'state' => 'state-abc']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'stranger@gmail.com']);
    }

    public function test_allow_list_permits_a_listed_account(): void
    {
        config(['services.gmail.allowed_emails' => 'a@x.test, ridzkyan0504@gmail.com , b@x.test']);
        User::factory()->create(['email' => 'someone@else.test']);

        Http::fake($this->fakeToken(['email' => 'ridzkyan0504@gmail.com']));

        $this->withSession(['google_login_state' => 'state-abc'])
            ->get(route('auth.google.callback', ['code' => 'auth-code', 'state' => 'state-abc']))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_first_ever_user_can_bootstrap_a_fresh_install(): void
    {
        $this->assertSame(0, User::count());

        Http::fake($this->fakeToken(['email' => 'first@gmail.com']));

        $this->withSession(['google_login_state' => 'state-abc'])
            ->get(route('auth.google.callback', ['code' => 'auth-code', 'state' => 'state-abc']))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $this->assertSame(1, User::count());
    }

    public function test_token_exchange_failure_does_not_log_the_user_in(): void
    {
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400)]);

        $this->withSession(['google_login_state' => 'state-abc'])
            ->get(route('auth.google.callback', ['code' => 'bad', 'state' => 'state-abc']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_the_login_callback_uri_is_sent_to_google_during_exchange(): void
    {
        Http::fake($this->fakeToken(['email' => 'ridzkyan0504@gmail.com']));

        $this->withSession(['google_login_state' => 'state-abc'])
            ->get(route('auth.google.callback', ['code' => 'auth-code', 'state' => 'state-abc']));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'oauth2.googleapis.com/token')
                && ($request['redirect_uri'] ?? null) === 'http://localhost/auth/google/callback';
        });
    }

    public function test_single_legacy_account_is_reused_so_no_duplicate_is_created(): void
    {
        $legacy = User::factory()->create(['email' => 'kyan@gawetracker.test', 'name' => 'Kyan']);

        Http::fake($this->fakeToken(['email' => 'ridzkyan0504@gmail.com', 'name' => 'Ridzky An']));

        $this->withSession(['google_login_state' => 'state-abc'])
            ->get(route('auth.google.callback', ['code' => 'auth-code', 'state' => 'state-abc']))
            ->assertRedirect(route('dashboard'));

        // Same row, re-keyed to the Google email — not a second account.
        $this->assertSame(1, User::count());
        $this->assertAuthenticatedAs($legacy);
        $this->assertSame('ridzkyan0504@gmail.com', $legacy->fresh()->email);
        $this->assertTrue($legacy->fresh()->hasGmailConnected());
    }

    public function test_legacy_account_is_not_adopted_when_several_users_exist(): void
    {
        User::factory()->create(['email' => 'kyan@gawetracker.test']);
        User::factory()->create(['email' => 'someone@else.test']);

        Http::fake($this->fakeToken(['email' => 'ridzkyan0504@gmail.com']));

        $this->withSession(['google_login_state' => 'state-abc'])
            ->get(route('auth.google.callback', ['code' => 'auth-code', 'state' => 'state-abc']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertGuest();
        $this->assertSame(2, User::count());
    }
}
