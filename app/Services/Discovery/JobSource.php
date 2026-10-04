<?php

namespace App\Services\Discovery;

/**
 * Contract every job source must implement. Keeps Glints, Jobstreet (and any
 * future board) interchangeable, so the discovery command never needs to know
 * which board it is talking to.
 */
interface JobSource
{
    /**
     * Short identifier used for the `source` column ('glints', 'jobstreet').
     */
    public function name(): string;

    /**
     * Fetch postings for a keyword. Implementations must:
     *  - never throw on an empty result set (return []),
     *  - send browser-like headers where the board requires them,
     *  - return DiscoveredJob instances only (no raw arrays).
     *
     * @return array<int, DiscoveredJob>
     */
    public function search(string $keyword, int $limit = 30): array;
}
