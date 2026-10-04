<?php

namespace App\Services\Discovery;

use App\Models\Company;
use App\Models\JobPosting;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use App\Support\SalaryParser;

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

        $attributes = [
            'source' => $job->source,
            'external_id' => $job->externalId,
        ];

        $posting = JobPosting::firstOrNew($attributes);
        $isNew = ! $posting->exists;

        $this->fillPosting($posting, $job, $company);

        try {
            $posting->save();
        } catch (UniqueConstraintViolationException) {
            // A concurrent search inserted the same (user, source, external_id)
            // between our lookup and insert. The unique index rejected our
            // write, but the row now exists: re-read it and refresh it so the
            // caller still gets the canonical posting instead of a 500.
            $posting = JobPosting::where($attributes)->first();

            if ($posting === null) {
                throw new \RuntimeException(
                    "Job posting {$job->source}:{$job->externalId} vanished after a unique constraint violation."
                );
            }

            $isNew = false;

            $this->fillPosting($posting, $job, $company);
            $posting->save();
        }

        return $isNew;
    }

    /**
     * Copy a discovered job's fields onto a posting model.
     */
    private function fillPosting(JobPosting $posting, DiscoveredJob $job, ?Company $company): void
    {
        $posting->fill([
            'title' => $job->title,
            'company' => $job->company,
            'company_id' => $company?->id,
            'location' => $job->location,
            'description' => $job->description,
            'source_url' => $job->sourceUrl,
            'salary_note' => $job->salaryNote,
            'posted_at' => $this->parseDate($job->postedAt),
        ]);

        // Keep the board's prose in salary_note, but store the numbers we can
        // read from it so the list can sort/filter by pay.
        $salary = SalaryParser::parse($job->salaryNote);
        $posting->fill($salary?->toColumns() ?? [
            'salary_min' => null,
            'salary_max' => null,
            'salary_currency' => null,
            'salary_period' => null,
        ]);
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
