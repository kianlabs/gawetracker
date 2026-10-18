<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ValidateBotApiToken
{
    /**
     * Handle an incoming request for Bot API.
     *
     * Authenticates the user via:
     * - Header: `X-API-Key: <token>`
     * - Header: `Authorization: Bearer <token>`
     * - Query string / payload: `api_token=<token>`
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-API-Key')
            ?? $request->bearerToken()
            ?? $request->input('api_token');

        if (! empty($token)) {
            $user = User::where('api_token', $token)->first();
            if ($user) {
                Auth::login($user);
                $request->setUserResolver(fn () => $user);

                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => 'API Token tidak valid atau tidak disertakan. Sertakan header X-API-Key atau Authorization: Bearer <token>. Token dapat dilihat di dashboard GaweTracker menu Integrasi Bot.',
            ],
        ], 401);
    }
}
