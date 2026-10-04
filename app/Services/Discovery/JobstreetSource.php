<?php

namespace App\Services\Discovery;

use Illuminate\Support\Facades\Http;

/**
 * Jobstreet / SEEK job source.
 *
 * Jobstreet (jobstreet.com, jobstreet.co.id, ...), SEEK (seek.com.au,
 * seek.co.nz) and JobsDB all run on the same SEEK platform. The legacy
 * `chalice-search` v4 endpoint was retired; the current public JSON API is the
 * v5 JobSearch endpoint at `/api/jobsearch/v5/search`. The HTML pages sit behind
 * Cloudflare but the JSON API does not, so a browser-like User-Agent is enough.
 *
 * The v5 result item shape (only the fields we consume):
 *   id, title,
 *   advertiser: { description }, companyName,
 *   locations: [ { label } ],
 *   listingDate, salaryLabel,
 *   ...
 *
 * The endpoint/site key are overridable so the same source can target any SEEK
 * market: ID-Main (Indonesia), SG-Main, MY-Main, PH-Main, HK-Main, AU-Main…
 */
class JobstreetSource implements JobSource
{
    /** SEEK v5 JobSearch endpoint for the Indonesian board. */
    public const DEFAULT_ENDPOINT = 'https://id.jobstreet.com/api/jobsearch/v5/search';

    /** SEEK site key: ID-Main (Indonesia), SG-Main, MY-Main, HK-Main, AU-Main… */
    public const DEFAULT_SITE_KEY = 'ID-Main';

    /** SEEK caps a page at 30 items; ask for the maximum and page through. */
    private const PAGE_SIZE = 30;

    private const TIMEOUT = 15;

    private const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) '
        .'AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4.1 Safari/605.1.15';

    /** Hosts we will build a job URL for (guards against a hostile endpoint). */
    private const ALLOWED_HOSTS = [
        'id.jobstreet.com',
        'www.jobstreet.com',
        'www.jobstreet.co.id',
        'jobstreet.com',
        'jobstreet.co.id',
        'sg.jobstreet.com',
        'my.jobstreet.com',
        'hk.jobsdb.com',
        'www.seek.com.au',
        'www.seek.co.nz',
    ];

    /** Hosts whose job detail pages live under the `/id/` locale prefix. */
    private const ID_LOCALE_HOSTS = [
        'id.jobstreet.com',
        'www.jobstreet.co.id',
        'jobstreet.co.id',
    ];

    private readonly string $endpoint;

    private readonly string $siteKey;

    public function __construct(?string $endpoint = null, ?string $siteKey = null)
    {
        $this->endpoint = $endpoint ?? config('services.jobstreet.endpoint', self::DEFAULT_ENDPOINT);
        $this->siteKey = $siteKey ?? config('services.jobstreet.site_key', self::DEFAULT_SITE_KEY);
    }

    public function name(): string
    {
        return 'jobstreet';
    }

    /**
     * Fetch up to $limit postings for a keyword, paging through the v5 API.
     *
     * @return array<int, DiscoveredJob>
     */
    public function search(string $keyword, int $limit = 30): array
    {
        $limit = max(1, $limit);
        $pageSize = min($limit, self::PAGE_SIZE);
        $maxPages = (int) ceil($limit / $pageSize);

        $jobs = [];

        for ($page = 1; $page <= $maxPages; $page++) {
            $items = $this->fetchPage($keyword, $page, $pageSize);

            if ($items === []) {
                break;
            }

            foreach ($items as $item) {
                if (($job = $this->parseItem($item)) !== null) {
                    $jobs[] = $job;
                }

                if (count($jobs) >= $limit) {
                    return $jobs;
                }
            }

            // A short page means we have reached the end of the results.
            if (count($items) < $pageSize) {
                break;
            }
        }

        return $jobs;
    }

    /**
     * Map a single v5 result item into a DiscoveredJob, or null when it lacks
     * the identity we need (a job id and a title).
     *
     * Public so the mapping can be unit-tested without a live HTTP call.
     *
     * @param  array<string, mixed>  $item
     */
    public function parseItem(array $item): ?DiscoveredJob
    {
        $id = trim((string) ($item['id'] ?? ''));
        $title = trim((string) ($item['title'] ?? ''));

        if ($id === '' || $title === '') {
            return null;
        }

        return new DiscoveredJob(
            source: $this->name(),
            externalId: $id,
            title: $title,
            company: $this->company($item),
            location: $this->location($item),
            sourceUrl: $this->jobUrl($id),
            salaryNote: $this->string($item['salaryLabel'] ?? null),
            postedAt: $this->string($item['listingDate'] ?? null),
            description: $this->string($item['teaser'] ?? null),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchPage(string $keyword, int $page, int $pageSize): array
    {
        $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
            ->acceptJson()
            ->timeout(self::TIMEOUT)
            ->get($this->endpoint, [
                'siteKey' => $this->siteKey,
                'keywords' => $keyword,
                'page' => $page,
                'pageSize' => $pageSize,
            ]);

        if ($response->failed()) {
            // The orchestrator catches Throwable per source and records it, so a
            // hard failure surfaces as a warning instead of aborting the run.
            throw new \RuntimeException(sprintf(
                'Jobstreet API request failed (HTTP %d) for "%s".',
                $response->status(),
                $keyword,
            ));
        }

        $data = $response->json('data');

        return is_array($data) ? array_values(array_filter($data, 'is_array')) : [];
    }

    /**
     * Prefer the branded advertiser name, then the shorter company name.
     *
     * @param  array<string, mixed>  $item
     */
    private function company(array $item): string
    {
        foreach ([
            $item['advertiser']['description'] ?? null,
            $item['companyName'] ?? null,
            $item['employer']['name'] ?? null,
        ] as $candidate) {
            if (($value = $this->string($candidate)) !== null) {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function location(array $item): ?string
    {
        return $this->string($item['locations'][0]['label'] ?? null);
    }

    private function jobUrl(string $externalId): ?string
    {
        $host = parse_url($this->endpoint, PHP_URL_HOST);
        $scheme = parse_url($this->endpoint, PHP_URL_SCHEME);

        if (! is_string($host) || $scheme !== 'https' || ! in_array($host, self::ALLOWED_HOSTS, true)) {
            return null;
        }

        $prefix = in_array($host, self::ID_LOCALE_HOSTS, true) ? '/id/job/' : '/job/';

        return "https://{$host}{$prefix}{$externalId}";
    }

    private function string(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
