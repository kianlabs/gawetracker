<?php

namespace App\Services;

use App\Models\Company;
use App\Models\IngestedEmail;
use App\Models\JobApplication;
use App\Support\Company\CompanyNameNormalizer;
use App\Support\Email\JobEmailParser;
use App\Support\Email\ParsedJobEmail;
use Illuminate\Support\Facades\DB;

/**
 * Turns parsed job emails into JobApplication rows and status transitions.
 *
 * Design rules:
 *  - Idempotent: an email whose Message-ID was already ingested is skipped.
 *  - Non-destructive: a status change is only applied when it moves the
 *    application *forward* in the pipeline, so a stale email can never downgrade
 *    a candidate who is already further along.
 *  - Every transition is written to StatusHistory, mirroring the manual flow.
 */
class EmailIngestionService
{
    /**
     * Pipeline order used to decide whether a status is a forward move.
     *
     * @var array<string, int>
     */
    private const STATUS_RANK = [
        'wishlist' => 0,
        'applied' => 1,
        'screening' => 2,
        'interview' => 3,
        'offer' => 4,
        'hired' => 5,
    ];

    public function __construct(private readonly JobEmailParser $parser = new JobEmailParser) {}

    /**
     * Ingest a single raw RFC 822 message. Returns the ledger row, or null when
     * the email was already processed or is not a job email.
     */
    public function ingestRaw(string $raw): ?IngestedEmail
    {
        return $this->ingest($this->parser->parseRaw($raw));
    }

    /**
     * Ingest an already-parsed email.
     */
    public function ingest(ParsedJobEmail $parsed): ?IngestedEmail
    {
        // Only board mails we understand, and only ones with a real status.
        if ($parsed->provider === 'unknown' || ! $parsed->hasStatus()) {
            return null;
        }

        // Idempotency guard.
        if (IngestedEmail::where('message_id', $parsed->messageId)->exists()) {
            return null;
        }

        return DB::transaction(function () use ($parsed) {
            $application = $this->matchOrCreateApplication($parsed);

            $ledger = IngestedEmail::create([
                'message_id' => $parsed->messageId,
                'provider' => $parsed->provider,
                'from_address' => $parsed->from,
                'subject' => $parsed->subject,
                'received_at' => $parsed->receivedAt,
                'classified_status' => $parsed->status,
                'job_application_id' => $application?->id,
                'raw_snippet' => $parsed->snippet,
            ]);

            if ($application !== null && $parsed->status !== null) {
                $this->applyStatus($application, $parsed->status, $parsed->subject, $parsed->receivedAt);
            }

            return $ledger;
        });
    }

    /**
     * Find the most likely application for this email, or create a new one when
     * the email clearly reports a fresh application.
     */
    private function matchOrCreateApplication(ParsedJobEmail $parsed): ?JobApplication
    {
        $application = $this->findExisting($parsed);

        if ($application !== null) {
            return $application;
        }

        // Only an "applied" email should create a brand-new row; a lone
        // "rejected"/"interview" mail for an unknown job is not enough context.
        if ($parsed->status !== 'applied' || $parsed->company === null) {
            return null;
        }

        $company = Company::findOrCreateByName($parsed->company, $this->domainFromUrl($parsed->sourceUrl));

        return JobApplication::create([
            'company' => $parsed->company,
            'company_id' => $company?->id,
            'position' => $parsed->position ?? 'Posisi tidak diketahui',
            'status' => 'applied',
            'source' => $this->sourceLabel($parsed->provider),
            'source_url' => $parsed->sourceUrl,
            'applied_at' => $parsed->receivedAt?->format('Y-m-d') ?? now()->toDateString(),
            'last_status_change_at' => now(),
            'notes' => 'Diimpor otomatis dari email '.$this->sourceLabel($parsed->provider).'.',
        ]);
    }

    /**
     * Locate an existing application by canonical company (and position when
     * available).
     *
     * Matching goes through the normalised company key so "PT Tokopedia",
     * "Tokopedia, PT" and "tokopedia.com" resolve to the same employer instead
     * of creating duplicate rows. Legacy rows with a NULL company_id are
     * matched by their free-text name and backfilled on the way through.
     */
    private function findExisting(ParsedJobEmail $parsed): ?JobApplication
    {
        if ($parsed->company === null) {
            return null;
        }

        $key = CompanyNameNormalizer::key($parsed->company);

        if ($key === '') {
            return null;
        }

        $company = Company::findOrCreateByName($parsed->company);

        // Rows already linked to this company...
        $linked = $company !== null
            ? JobApplication::query()->where('company_id', $company->id)->get()
            : collect();

        // ...plus legacy rows (no company_id) whose name normalises the same.
        $legacy = JobApplication::query()
            ->whereNull('company_id')
            ->get()
            ->filter(fn (JobApplication $app): bool => CompanyNameNormalizer::key($app->company) === $key);

        $candidates = $linked->concat($legacy);

        if ($candidates->isEmpty()) {
            return null;
        }

        $match = null;

        if ($parsed->position !== null) {
            $position = mb_strtolower($parsed->position);
            $match = $candidates
                ->filter(fn (JobApplication $app): bool => mb_strtolower((string) $app->position) === $position)
                ->sortByDesc('id')
                ->first();
        }

        $match ??= $candidates->sortByDesc('id')->first();

        // Backfill the canonical link on legacy rows so future matches are cheap.
        if ($match !== null && $match->company_id === null && $company !== null) {
            $match->company_id = $company->id;
            $match->save();
        }

        return $match;
    }

    /**
     * Extract a bare hostname from a source URL, for company domain enrichment.
     */
    private function domainFromUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? preg_replace('/^www\./', '', $host) : null;
    }

    /**
     * Apply a forward-only status transition, recording history.
     */
    private function applyStatus(JobApplication $application, string $status, ?string $subject, ?\DateTimeInterface $at): void
    {
        $current = $application->status;

        // Terminal states are never overwritten by a later email.
        if (in_array($current, ['hired', 'rejected'], true)) {
            return;
        }

        // Ignore moves that are not forward (stale / out-of-order emails).
        $currentRank = self::STATUS_RANK[$current] ?? 0;
        $newRank = $status === 'rejected' ? PHP_INT_MAX : (self::STATUS_RANK[$status] ?? 0);

        if ($newRank <= $currentRank && $status !== 'rejected') {
            return;
        }

        $application->status = $status;
        $application->last_status_change_at = now();
        $application->save();

        $application->statusHistories()->create([
            'from_status' => $current,
            'to_status' => $status,
            'note' => 'Diperbarui otomatis dari email: '.($subject ?? '(tanpa subjek)'),
            'created_at' => $at ?? now(),
        ]);
    }

    private function sourceLabel(string $provider): string
    {
        return match ($provider) {
            'jobstreet' => 'JobStreet',
            'glints' => 'Glints',
            default => ucfirst($provider),
        };
    }
}
