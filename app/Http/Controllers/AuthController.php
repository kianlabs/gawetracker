<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Display the login form.
     */
    public function showLoginForm(): View
    {
        return view('auth.login');
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
     * Display the registration form.
     *
     * Registration can be closed instance-wide with REGISTRATION_ENABLED=false;
     * a closed instance answers 404 rather than advertising a form nobody may use.
     */
    public function showRegisterForm(): View
    {
        abort_unless(config('auth.registration_enabled'), 404);

        return view('auth.register');
    }

    /**
     * Create a new account and sign the user in.
     *
     * The new account starts empty — every record it owns is stamped with its
     * user id by the BelongsToUser hook.
     *
     * @throws ValidationException
     */
    public function register(Request $request): RedirectResponse
    {
        abort_unless(config('auth.registration_enabled'), 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah terdaftar.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $user = User::create($validated);

        Auth::login($user);
        $request->session()->regenerate();

        // Send the verification link when enforcement is on AND a real mailer
        // is configured. With MAIL_MAILER=log/array the mail only lands in the
        // log/void, so there is nothing to send and no point slowing signup.
        if (config('auth.email_verification_enabled')
            && ! in_array(config('mail.default'), ['log', 'array'], true)) {
            $user->sendEmailVerificationNotification();
        }

        return redirect()->route('dashboard');
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
}
