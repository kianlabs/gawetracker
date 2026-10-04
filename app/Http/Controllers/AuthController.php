<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\GoogleOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(private readonly GoogleOAuthService $oauth) {}

    /**
     * Session key holding the CSRF state for the sign-in flow. Distinct from the
     * connect-Gmail flow so the two can never be confused.
     */
    private const STATE_KEY = 'google_login_state';

    /**
     * Display the login form.
     */
    public function showLoginForm(): View
    {
        return view('auth.login', [
            'googleEnabled' => $this->oauth->isConfigured(),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     *
     * @throws ValidationException
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Password wajib diisi.',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi yang Anda masukkan salah.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Start "Sign in with Google".
     *
     * The scope includes gmail.readonly, so a successful sign-in also links the
     * mailbox: there is no separate "connect Gmail" step afterwards.
     */
    public function redirectToGoogle(): RedirectResponse
    {
        if (! $this->oauth->isConfigured()) {
            return redirect()->route('login')
                ->with('error', 'Login Google belum dikonfigurasi. Jalankan `php artisan gmail:setup`.');
        }

        $state = Str::random(40);
        session([self::STATE_KEY => $state]);

        return redirect()->away($this->oauth->loginAuthorizationUrl($state));
    }

    /**
     * Handle the Google callback: verify state, exchange the code, then log the
     * user in (creating the account on first sign-in) with the mailbox linked.
     */
    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        // User denied consent, or Google returned an error.
        if ($request->filled('error')) {
            return redirect()->route('login')->with('error', 'Login Google dibatalkan.');
        }

        $expected = session()->pull(self::STATE_KEY);
        if (! $expected || ! hash_equals($expected, (string) $request->input('state'))) {
            return redirect()->route('login')->with('error', 'Sesi login Google tidak valid. Silakan coba lagi.');
        }

        $code = (string) $request->input('code');
        if ($code === '') {
            return redirect()->route('login')->with('error', 'Google tidak mengirim kode otorisasi.');
        }

        // Exchange the code first — it also yields the id_token with the email.
        $payload = $this->oauth->exchangeCode($code, $this->oauth->loginRedirectUri());
        if ($payload === null) {
            return redirect()->route('login')->with('error', 'Gagal menukar kode otorisasi dengan token.');
        }

        $email = $this->oauth->emailFromIdToken((string) ($payload['id_token'] ?? ''));
        if ($email === null || $email === '') {
            return redirect()->route('login')->with('error', 'Google tidak mengirim alamat email.');
        }

        if (! $this->maySignIn($email)) {
            return redirect()->route('login')
                ->with('error', "Akun {$email} tidak diizinkan. Tambahkan ke daftar pengguna yang diizinkan.");
        }

        $user = User::firstOrNew(['email' => $email]);

        // First sign-in: create the account. A random password is stored so the
        // legacy password form cannot be used to log in as this user.
        if (! $user->exists) {
            // Single-user install upgrading from password auth: reuse the
            // existing account instead of creating a duplicate, and avoid the
            // sign-in gate locking the operator out (their Google email is not
            // in the users table yet). Only when it is unambiguous — exactly
            // one existing user, still on password auth (no mailbox linked).
            $legacy = $this->legacyAccountToAdopt();

            if ($legacy !== null) {
                $legacy->email = $email;
                $legacy->email_verified_at ??= now();
                $user = $legacy;
            } else {
                $user->name = $this->oauth->nameFromIdToken((string) ($payload['id_token'] ?? '')) ?? Str::before($email, '@');
                $user->password = Str::random(64);
                $user->email_verified_at = now();
            }
        }

        // Persist tokens onto the user (also sets gmail_email + connected_at,
        // and saves the model).
        $this->oauth->storeTokens($user, $payload);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'))
            ->with('success', 'Berhasil masuk dengan Google. Email lamaran akan otomatis dipantau.');
    }

    /**
     * Destroy an authenticated session.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * The single pre-existing password-auth account that a first Google
     * sign-in may reuse instead of creating a duplicate.
     *
     * Returns null unless the situation is unambiguous: exactly one user in
     * total, and that user has not linked a mailbox yet (i.e. still on the old
     * password flow). Anything else — several users, or a mailbox already
     * linked — means we create a fresh account instead of guessing.
     */
    private function legacyAccountToAdopt(): ?User
    {
        if (User::count() !== 1) {
            return null;
        }

        $only = User::sole();

        if ($only->gmail_email !== null || $only->gmail_refresh_token !== null) {
            return null;
        }

        return $only;
    }

    /**
     * Who may sign in. This is a single-user app, so access is restricted to:
     *  - an explicit allow-list (`GAWETRACKER_ALLOWED_EMAILS`, comma separated),
     *  - an email that already has an account, or
     *  - the very first user, to bootstrap a fresh install.
     *
     * Without this gate, "Sign in with Google" would be open registration.
     */
    private function maySignIn(string $email): bool
    {
        $email = Str::lower($email);

        $allowed = collect(explode(',', (string) config('services.gmail.allowed_emails')))
            ->map(fn ($value) => Str::lower(trim($value)))
            ->filter()
            ->all();

        if (in_array($email, $allowed, true)) {
            return true;
        }

        if (User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
            return true;
        }

        // When an allow-list is configured it is the sole authority: no
        // bootstrap or adoption may bypass it.
        if ($allowed !== []) {
            return false;
        }

        // Fresh install (no users yet) or a single-user install upgrading from
        // password auth that this sign-in will adopt.
        return User::count() === 0 || $this->legacyAccountToAdopt() !== null;
    }
}
