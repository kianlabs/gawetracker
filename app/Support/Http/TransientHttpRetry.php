<?php

namespace App\Support\Http;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

/**
 * Shared retry policy for the job boards.
 *
 * Boards occasionally answer 500/502/503 or drop the connection under load, and
 * a transient blip should not cost a whole discovery run. We retry only those
 * transient conditions — a 4xx (bad keyword, blocked request, retired endpoint)
 * is deterministic, so retrying it just wastes time and hammers the board.
 */
final class TransientHttpRetry
{
    /** Total attempts (1 initial + 2 retries). */
    public const TIMES = 3;

    /** Base delay between attempts, in milliseconds (Laravel backs off from here). */
    public const DELAY_MS = 500;

    /**
     * Predicate for `Http::retry(..., when: ...)`: given the exception for a
     * failed attempt, decide whether another try is worthwhile.
     */
    public static function when(): Closure
    {
        return static function (\Throwable $e): bool {
            if ($e instanceof ConnectionException) {
                return true; // timeout / DNS / connection reset
            }

            if ($e instanceof RequestException && $e->response !== null) {
                $status = $e->response->status();

                return $status >= 500 || $status === 429; // server fault or rate limit
            }

            return false;
        };
    }
}
