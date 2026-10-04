<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Google OAuth2 helper for linking a Gmail mailbox (read-only).
 *
 * Implements the authorization-code flow plus refresh-token rotation. The
 * refresh token is what lets `emails:import --gmail` keep working after the
 * 1-hour access token expires, with no user interaction.
 */
class GoogleOAuthService
{
    private const AUTH_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    /** Read-only Gmail access — the minimum needed to ingest mail. */
    public const SCOPES = [
        'https://www.googleapis.com/auth/gmail.readonly',
        'openid',
        'email',
    ];

    /**
     * Whether the OAuth client is configured (client id + secret present).
     */
    public function isConfigured(): bool
    {
        return ! empty(config('services.gmail.client_id'))
            && ! empty(config('services.gmail.client_secret'))
            && ! empty($this->redirectUri());
    }

    public function redirectUri(): string
    {
        return (string) (config('services.gmail.redirect_uri') ?: route('gmail.callback'));
    }

    /**
     * Callback URI for the "Sign in with Google" flow. Distinct from the
     * connect-Gmail callback so the two flows are easy to tell apart in logs;
     * both must be registered on the same OAuth client.
     */
    public function loginRedirectUri(): string
    {
        return (string) (config('services.gmail.login_redirect_uri') ?: route('auth.google.callback'));
    }

    /**
     * Build the Google consent URL. `state` is stored in the session by the
     * caller and verified on callback to prevent CSRF.
     */
    public function authorizationUrl(string $state): string
    {
        return $this->buildAuthorizationUrl($state, $this->redirectUri());
    }

    /**
     * Consent URL for the sign-in flow (same scopes, login callback).
     */
    public function loginAuthorizationUrl(string $state): string
    {
        return $this->buildAuthorizationUrl($state, $this->loginRedirectUri());
    }

    private function buildAuthorizationUrl(string $state, string $redirectUri): string
    {
        return self::AUTH_ENDPOINT.'?'.http_build_query([
            'client_id' => config('services.gmail.client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'access_type' => 'offline',   // required to receive a refresh token
            'prompt' => 'consent',        // force refresh token on re-connect
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    public function generateState(): string
    {
        return Str::random(40);
    }

    /**
     * Exchange an authorization code for tokens and persist them on the user.
     * Returns true on success.
     *
     * $redirectUri must match the one used to obtain the code — Google rejects
     * the exchange otherwise. Defaults to the connect-Gmail callback.
     */
    public function connect(User $user, string $code, ?string $redirectUri = null): bool
    {
        $payload = $this->exchangeCode($code, $redirectUri);

        if ($payload === null) {
            return false;
        }

        $this->storeTokens($user, $payload);

        return true;
    }

    /**
     * Exchange an authorization code for a token payload, without touching any
     * user. Returns null when Google rejects the exchange.
     *
     * Exposed so the sign-in flow can read the id_token (email/name) before it
     * decides whether to create or update the user.
     *
     * @return array<string, mixed>|null
     */
    public function exchangeCode(string $code, ?string $redirectUri = null): ?array
    {
        $response = Http::asForm()->timeout(30)->post(self::TOKEN_ENDPOINT, [
            'code' => $code,
            'client_id' => config('services.gmail.client_id'),
            'client_secret' => config('services.gmail.client_secret'),
            'redirect_uri' => $redirectUri ?? $this->redirectUri(),
            'grant_type' => 'authorization_code',
        ]);

        if (! $response->successful()) {
            return null;
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : null;
    }

    /**
     * Return a valid access token for the user, refreshing it if it is missing
     * or about to expire. Returns null when the user has no connection.
     */
    public function freshAccessToken(User $user): ?string
    {
        if (! $user->hasGmailConnected()) {
            return null;
        }

        // 2-minute safety margin so a token doesn't expire mid-request.
        if ($user->gmail_access_token
            && $user->gmail_token_expires_at
            && $user->gmail_token_expires_at->isAfter(now()->addMinutes(2))) {
            return $user->gmail_access_token;
        }

        return $this->refresh($user);
    }

    /**
     * Exchange the refresh token for a new access token.
     */
    public function refresh(User $user): ?string
    {
        $response = Http::asForm()->timeout(30)->post(self::TOKEN_ENDPOINT, [
            'client_id' => config('services.gmail.client_id'),
            'client_secret' => config('services.gmail.client_secret'),
            'refresh_token' => $user->gmail_refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful()) {
            return null;
        }

        $this->storeTokens($user, $response->json(), keepRefreshToken: true);

        return $user->fresh()->gmail_access_token;
    }

    /**
     * Persist the token payload on the user.
     *
     * Public so the sign-in flow can apply tokens through the same code path as
     * the connect-Gmail flow.
     *
     * @param  array<string, mixed>  $payload
     */
    public function storeTokens(User $user, array $payload, bool $keepRefreshToken = false): void
    {
        $accessToken = $payload['access_token'] ?? null;
        if ($accessToken === null) {
            return;
        }

        $user->gmail_access_token = $accessToken;
        $user->gmail_token_expires_at = now()->addSeconds((int) ($payload['expires_in'] ?? 3600));

        // A refresh token is only returned on the first consent (or with
        // prompt=consent). Never overwrite an existing one with null.
        if (! empty($payload['refresh_token'])) {
            $user->gmail_refresh_token = $payload['refresh_token'];
        } elseif (! $keepRefreshToken && ! $user->gmail_refresh_token) {
            $user->gmail_refresh_token = null;
        }

        if (! empty($payload['id_token'])) {
            $email = $this->emailFromIdToken($payload['id_token']);
            if ($email) {
                $user->gmail_email = $email;
            }
        }

        if ($user->gmail_connected_at === null) {
            $user->gmail_connected_at = now();
        }

        $user->save();
    }

    /**
     * Pull the email claim out of a JWT id_token (no signature check needed —
     * it comes straight from Google over TLS).
     */
    public function emailFromIdToken(string $idToken): ?string
    {
        return $this->claimFromIdToken($idToken, 'email');
    }

    /**
     * Pull the display name out of a JWT id_token, when Google provides one.
     */
    public function nameFromIdToken(string $idToken): ?string
    {
        return $this->claimFromIdToken($idToken, 'name');
    }

    private function claimFromIdToken(string $idToken, string $claim): ?string
    {
        $parts = explode('.', $idToken);
        if (count($parts) < 2) {
            return null;
        }

        $payload = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/')), true);

        if (! is_array($payload) || empty($payload[$claim]) || ! is_string($payload[$claim])) {
            return null;
        }

        return $payload[$claim];
    }
}
