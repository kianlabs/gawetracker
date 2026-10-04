<?php

namespace App\Services\Discovery;

/**
 * A single job posting discovered from an external source, normalised into the
 * shape GaweTracker stores. Immutable value object so sources stay pure and
 * testable without touching the database.
 */
final class DiscoveredJob
{
    public function __construct(
        public readonly string $source,        // 'glints' | 'jobstreet'
        public readonly string $externalId,    // source's own job id
        public readonly string $title,
        public readonly string $company,
        public readonly ?string $location = null,
        public readonly ?string $sourceUrl = null,
        public readonly ?string $salaryNote = null,
        public readonly ?string $postedAt = null,   // ISO-8601 string, nullable
    ) {}

    /**
     * Stable dedup key across sources: source + external id.
     */
    public function dedupKey(): string
    {
        return $this->source.':'.$this->externalId;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'external_id' => $this->externalId,
            'title' => $this->title,
            'company' => $this->company,
            'location' => $this->location,
            'source_url' => $this->sourceUrl,
            'salary_note' => $this->salaryNote,
            'posted_at' => $this->postedAt,
        ];
    }
}
