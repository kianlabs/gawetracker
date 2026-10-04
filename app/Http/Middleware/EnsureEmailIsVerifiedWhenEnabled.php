<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforce a verified email only when verification is switched on.
 *
 * Laravel's own `verified` middleware always blocks unverified users. This
 * deployment starts with EMAIL_VERIFICATION_ENABLED=false because there is no
 * mailer configured yet, so blocking would lock every existing account — and
 * the owner — out of the app. This middleware reads the flag per request:
 * while it is off it is a no-op, and once a real mailer is configured the
 * behaviour matches the framework middleware exactly.
 */
class EnsureEmailIsVerifiedWhenEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('auth.email_verification_enabled')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            return $request->expectsJson()
                ? abort(403, 'Email Anda belum diverifikasi.')
                : redirect()->guest(route('verification.notice'));
        }

        return $next($request);
    }
}
