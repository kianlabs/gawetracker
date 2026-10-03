<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GoogleOAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GmailConnectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();

        config([
            'services.gmail.client_id' => 'test-client-id',
            'services.gmail.client_secret' => 'test-secret',
            'services.gmail.redirect_uri' => 'http://localhost/gmail/callback',
        ]);
    }

    public function test_guest_cannot_start_gmail_connection(): void
    {
        $this->get(route('gmail.connect'))->assertRedirect('/login');
    }

    public function test_connect_redirects_to_google_with_offline_access(): void
    {
        $response = $this->actingAs($this->user)->get(route('gmail.connect'));

        $response->assertRedirect();
        $location = $response->headers->get('Location');

        $this->assertStringContainsString('accounts.google.com', $location);
        $this->assertStringContainsString('access_type=offline', $location);
        $this->assertStringContainsString('gmail.readonly', $location);
        $this->assertStringContainsString('state=', $location);
    }

    public function test_callback_stores_tokens_and_marks_connected(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.access',
                'refresh_token' => '1//refresh',
                'expires_in' => 3600,
                'id_token' => $this->idToken(['email' => 'kyan@gmail.com']),
            ], 200),
        ]);

        // Seed the expected state as the connect step would.
        $response = $this->actingAs($this->user)
            ->withSession(['gmail_oauth_state' => 'state-123'])
            ->get(route('gmail.callback', ['code' => 'auth-code', 'state' => 'state-123']));

        $response->assertRedirect(route('dashboard'));

        $this->user->refresh();
        $this->assertTrue($this->user->hasGmailConnected());
        $this->assertSame('kyan@gmail.com', $this->user->gmail_email);
        $this->assertSame('ya29.access', $this->user->gmail_access_token);
        $this->assertNotNull($this->user->gmail_token_expires_at);
    }

    public function test_callback_rejects_mismatched_state(): void
    {
        Http::fake(); // must never be called

        $response = $this->actingAs($this->user)
            ->withSession(['gmail_oauth_state' => 'expected'])
            ->get(route('gmail.callback', ['code' => 'auth-code', 'state' => 'WRONG']));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');
        $this->assertFalse($this->user->fresh()->hasGmailConnected());
        Http::assertNothingSent();
    }

    public function test_disconnect_clears_tokens(): void
    {
        $this->user->forceFill([
            'gmail_access_token' => 'a',
            'gmail_refresh_token' => 'r',
            'gmail_token_expires_at' => now()->addHour(),
            'gmail_email' => 'kyan@gmail.com',
            'gmail_connected_at' => now(),
        ])->save();

        $this->actingAs($this->user)
            ->delete(route('gmail.disconnect'))
            ->assertRedirect(route('dashboard'));

        $this->user->refresh();
        $this->assertFalse($this->user->hasGmailConnected());
        $this->assertNull($this->user->gmail_email);
    }

    public function test_tokens_are_encrypted_at_rest(): void
    {
        $this->user->forceFill([
            'gmail_access_token' => 'plain-access',
            'gmail_refresh_token' => 'plain-refresh',
        ])->save();

        // Raw DB value must not equal the plaintext.
        $raw = \DB::table('users')->where('id', $this->user->id)->first();
        $this->assertNotSame('plain-refresh', $raw->gmail_refresh_token);
        $this->assertNotSame('plain-access', $raw->gmail_access_token);

        // But the model still returns the decrypted value.
        $this->assertSame('plain-refresh', $this->user->fresh()->gmail_refresh_token);
    }

    public function test_fresh_access_token_refreshes_when_expired(): void
    {
        $this->user->forceFill([
            'gmail_access_token' => 'stale',
            'gmail_refresh_token' => '1//refresh',
            'gmail_token_expires_at' => now()->subMinute(), // already expired
            'gmail_connected_at' => now(),
        ])->save();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'fresh-token',
                'expires_in' => 3600,
            ], 200),
        ]);

        $token = app(GoogleOAuthService::class)->freshAccessToken($this->user->fresh());

        $this->assertSame('fresh-token', $token);
        // The existing refresh token must be preserved across a refresh.
        $this->assertSame('1//refresh', $this->user->fresh()->gmail_refresh_token);
    }

    public function test_fresh_access_token_reuses_valid_token_without_calling_google(): void
    {
        $this->user->forceFill([
            'gmail_access_token' => 'still-good',
            'gmail_refresh_token' => '1//refresh',
            'gmail_token_expires_at' => now()->addHour(),
            'gmail_connected_at' => now(),
        ])->save();

        Http::fake();

        $token = app(GoogleOAuthService::class)->freshAccessToken($this->user->fresh());

        $this->assertSame('still-good', $token);
        Http::assertNothingSent();
    }

    /**
     * Build a minimal unsigned JWT with the given claims.
     *
     * @param  array<string, mixed>  $claims
     */
    private function idToken(array $claims): string
    {
        $b64 = fn (array $p) => rtrim(strtr(base64_encode(json_encode($p)), '+/', '-_'), '=');

        return $b64(['alg' => 'none']).'.'.$b64($claims).'.sig';
    }
}
