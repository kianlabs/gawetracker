<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Email verification flow for the MustVerifyEmail contract.
 *
 * These routes are always registered, but the app only links to them (and only
 * redirects unverified users here) when config('auth.email_verification_enabled')
 * is on. A deployment without a working mailer therefore never funnels users
 * into a dead end.
 */
class EmailVerificationController extends Controller
{
    /**
     * The "check your inbox" page for signed-in, unverified users.
     */
    public function notice(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        return view('auth.verify-email');
    }

    /**
     * Handle the signed link from the verification email.
     *
     * EmailVerificationRequest::authorize() rejects a tampered or expired hash,
     * which Laravel renders as a 403 instead of marking the account verified.
     */
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect()->route('dashboard')
            ->with('status', 'Email Anda berhasil diverifikasi.');
    }

    /**
     * Re-send the verification link to the signed-in user.
     */
    public function send(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'Tautan verifikasi baru sudah dikirim ke email Anda.');
    }
}
