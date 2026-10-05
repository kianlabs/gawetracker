<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GoogleOAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The per-user "Connect Gmail" OAuth flow.
 *
 * The Google endpoints are faked so the state check, token exchange and
 * encrypted-at-rest storage are exercised without any real credentials.
 */
class GmailConnectionTest extends TestCase
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
     * Build a fake Google id_token (header.payload.signature). Signature is
     * never verified by the app — it trusts Google over TLS — so a dummy is fine.
     *
     * @param  array<string, mixed>  $claims
     */
    private function idToken(array $claims): string
    {
        $b64 = fn (array $data): string => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

        return $b64(['alg' => 'RS256']).'.'.$b64($claims).'.signature';
    }

    /**
     * Fake the OAuth token endpoint returning a fresh token payload.
     */
    private function fakeTokenExchange(string $email = 'kyan@gmail.com'): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.access-token',
                'refresh_token' => '1//refresh-token',
                'expires_in' => 3600,
                'id_token' => $this->idToken(['email' => $email]),
            ]),
        ]);
    }

    public function test_a_guest_cannot_start_the_connect_flow(): void
    {
        $this->get(route('gmail.connect'))->assertRedirect(route('login'));
    }

    public function test_connect_redirects_to_google_with_offline_access(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('gmail.connect'));

        $response->assertRedirect();
        $location = $response->headers->get('Location');

        $this->assertStringStartsWith('https://accounts.google.com/', $location);
        $this->assertStringContainsString('access_type=offline', $location);
        $this->assertStringContainsString('prompt=consent', $location);
        $this->assertStringContainsString('gmail.readonly', urldecode($location));
    }

    public function test_connect_is_refused_when_the_client_is_not_configured(): void
    {
        config()->set('services.gmail.client_id', null);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->get(route('gmail.connect'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');
    }

    public function test_the_callback_stores_the_tokens_on_the_user(): void
    {
        $this->fakeTokenExchange('kyan@gmail.com');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['gmail_oauth_state' => 'the-state'])
            ->get(route('gmail.callback', ['code' => 'auth-code', 'state' => 'the-state']))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertSame('1//refresh-token', $user->gmail_refresh_token);
        $this->assertSame('ya29.access-token', $user->gmail_access_token);
        $this->assertSame('kyan@gmail.com', $user->gmail_email);
        $this->assertTrue($user->hasGmailConnected());
        $this->assertNotNull($user->gmail_connected_at);
        $this->assertNotNull($user->gmail_token_expires_at);
    }

    public function test_the_callback_rejects_a_mismatched_state(): void
    {
        $this->fakeTokenExchange();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['gmail_oauth_state' => 'expected'])
            ->get(route('gmail.callback', ['code' => 'auth-code', 'state' => 'tampered']))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');

        $this->assertNull($user->fresh()->gmail_refresh_token);
        Http::assertNothingSent();
    }

    public function test_the_callback_handles_a_denied_consent(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['gmail_oauth_state' => 'the-state'])
            ->get(route('gmail.callback', ['error' => 'access_denied', 'state' => 'the-state']))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');

        $this->assertNull($user->fresh()->gmail_refresh_token);
    }

    public function test_tokens_are_encrypted_at_rest(): void
    {
        $this->fakeTokenExchange();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['gmail_oauth_state' => 'the-state'])
            ->get(route('gmail.callback', ['code' => 'auth-code', 'state' => 'the-state']));

        $raw = DB::table('users')->where('id', $user->id)->first();

        // The stored value must not be the plaintext token, and must decrypt
        // back to it through the model cast.
        $this->assertNotSame('1//refresh-token', $raw->gmail_refresh_token);
        $this->assertStringNotContainsString('1//refresh-token', (string) $raw->gmail_refresh_token);
        $this->assertSame('1//refresh-token', $user->fresh()->gmail_refresh_token);
    }

    public function test_disconnect_clears_every_gmail_field(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'gmail_access_token' => 'access',
            'gmail_refresh_token' => 'refresh',
            'gmail_token_expires_at' => now()->addHour(),
            'gmail_email' => 'kyan@gmail.com',
            'gmail_connected_at' => now(),
        ])->save();

        $this->actingAs($user)
            ->delete(route('gmail.disconnect'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertNull($user->gmail_access_token);
        $this->assertNull($user->gmail_refresh_token);
        $this->assertNull($user->gmail_email);
        $this->assertFalse($user->hasGmailConnected());
    }

    public function test_fresh_access_token_reuses_a_still_valid_token(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'gmail_access_token' => 'still-valid',
            'gmail_refresh_token' => 'refresh',
            'gmail_token_expires_at' => now()->addHour(),
        ])->save();

        Http::fake();

        $token = app(GoogleOAuthService::class)->freshAccessToken($user);

        $this->assertSame('still-valid', $token);
        Http::assertNothingSent();
    }

    public function test_fresh_access_token_refreshes_an_expired_token(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'gmail_access_token' => 'expired',
            'gmail_refresh_token' => 'the-refresh-token',
            'gmail_token_expires_at' => now()->subMinute(),
        ])->save();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'brand-new',
                'expires_in' => 3600,
            ]),
        ]);

        $token = app(GoogleOAuthService::class)->freshAccessToken($user);

        $this->assertSame('brand-new', $token);
        $this->assertSame('brand-new', $user->fresh()->gmail_access_token);
        // A refresh must not wipe the stored refresh token.
        $this->assertSame('the-refresh-token', $user->fresh()->gmail_refresh_token);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'oauth2.googleapis.com/token')
            && $request['grant_type'] === 'refresh_token');
    }

    public function test_a_user_without_a_connection_has_no_access_token(): void
    {
        $user = User::factory()->create();

        Http::fake();

        $this->assertNull(app(GoogleOAuthService::class)->freshAccessToken($user));
        Http::assertNothingSent();
    }
}
