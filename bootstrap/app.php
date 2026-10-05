<?php

use App\Http\Middleware\EnsureEmailIsVerifiedWhenEnabled;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Gate the app behind a verified email only when verification is on;
        // a no-op otherwise so accounts predating a mailer are never locked out.
        $middleware->alias([
            'verified.when-enabled' => EnsureEmailIsVerifiedWhenEnabled::class,
        ]);

        // Behind Cloudflare Tunnel the app is served over plain HTTP from the
        // tunnel origin but the public request is HTTPS. Trust the forwarded
        // headers so Laravel generates https:// URLs and secure cookies.
        //
        // Only private addresses may act as proxies: cloudflared runs on this
        // host and connects over the docker network, so the request always
        // arrives from 172.16.0.0/12. Trusting '*' instead let a client forge
        // X-Forwarded-For and rotate the rate-limit key, bypassing the login
        // throttle. With the proxy list narrowed, forged entries on the left of
        // the header are discarded and the real client IP (which cloudflared
        // appends on the right) is used.
        $middleware->trustProxies(
            at: '172.16.0.0/12',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
