<?php

namespace App\Services\Discovery;

use App\Models\Company;
use App\Models\JobPosting;
use Illuminate\Support\Carbon;

/**
 * Orchestrates discovery across every registered JobSource and persists the
 * results idempotently.
 *
 * Dedup is layered:
 *  - (source, external_id) makes re-running the same board a no-op,
 *  - the canonical Company link keeps "PT Tokopedia" and "Tokopedia" as one.
 */
class JobDiscoveryService
{
    /**
     * @param  array<int, JobSource>  $sources
     */
    public function __construct(private readonly array $sources) {}

    /**
     * Run a keyword search across all sources.
     *
     * @return array{created: int, updated: int, seen: int, errors: array<int, string>}
     */
    public function discover(string $keyword, int $limit = 30): array
    {
        $created = 0;
        $updated = 0;
        $seen = 0;
        $errors = [];

        foreach ($this->sources as $source) {
            try {
                $jobs = $source->search($keyword, $limit);
            } catch (\Throwable $e) {
                // A single board failing must never abort the whole run.
                $errors[] = $source->name().': '.$e->getMessage();
                continue;
            }

            foreach ($jobs as $job) {
                $seen++;

                if ($this->persist($job)) {
                    $created++;
                } else {
                    $updated++;
                }
            }
        }

        return compact('created', 'updated', 'seen', 'errors');
    }

    /**
     * Insert or refresh a single posting. Returns true when a new row was made.
     */
    public function persist(DiscoveredJob $job): bool
    {
        $company = Company::findOrCreateByName($job->company);

        $posting = JobPosting::firstOrNew([
            'source' => $job->source,
            'external_id' => $job->externalId,
        ]);

        $isNew = ! $posting->exists;

        $posting->fill([
            'title' => $job->title,
            'company' => $job->company,
            'company_id' => $company?->id,
            'location' => $job->location,
            'source_url' => $job->sourceUrl,
            'salary_note' => $job->salaryNote,
            'posted_at' => $this->parseDate($job->postedAt),
        ]);

        $posting->save();

        return $isNew;
    }

    private function parseDate(?string $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
