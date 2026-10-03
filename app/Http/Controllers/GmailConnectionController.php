<?php

namespace App\Http\Controllers;

use App\Services\GoogleOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GmailConnectionController extends Controller
{
    public function __construct(private readonly GoogleOAuthService $oauth) {}

    /**
     * Redirect the user to Google's consent screen.
     */
    public function redirect(): RedirectResponse
    {
        if (! $this->oauth->isConfigured()) {
            return back()->with('error', 'Koneksi Gmail belum dikonfigurasi. Isi GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, dan GOOGLE_REDIRECT_URI di .env.');
        }

        $state = $this->oauth->generateState();
        session(['gmail_oauth_state' => $state]);

        return redirect()->away($this->oauth->authorizationUrl($state));
    }

    /**
     * Handle the OAuth callback, exchange the code and store the tokens.
     */
    public function callback(Request $request): RedirectResponse
    {
        // User denied consent, or Google returned an error.
        if ($request->filled('error')) {
            return redirect()->route('dashboard')
                ->with('error', 'Koneksi Gmail dibatalkan.');
        }

        // CSRF: the state we issued must match the one returned.
        $expected = session()->pull('gmail_oauth_state');
        if (! $expected || ! hash_equals($expected, (string) $request->input('state'))) {
            return redirect()->route('dashboard')
                ->with('error', 'Sesi OAuth tidak valid. Silakan coba lagi.');
        }

        $code = (string) $request->input('code');
        if ($code === '') {
            return redirect()->route('dashboard')
                ->with('error', 'Google tidak mengirim kode otorisasi.');
        }

        $user = Auth::user();
        if (! $this->oauth->connect($user, $code)) {
            return redirect()->route('dashboard')
                ->with('error', 'Gagal menukar kode otorisasi dengan token.');
        }

        return redirect()->route('dashboard')
            ->with('success', 'Gmail berhasil terhubung'.($user->fresh()->gmail_email ? ' ('.$user->fresh()->gmail_email.')' : '').'.');
    }

    /**
     * Disconnect the Gmail account and forget its tokens.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->forceFill([
            'gmail_access_token' => null,
            'gmail_refresh_token' => null,
            'gmail_token_expires_at' => null,
            'gmail_email' => null,
            'gmail_connected_at' => null,
        ])->save();

        return redirect()->route('dashboard')->with('success', 'Gmail berhasil diputuskan.');
    }
}
