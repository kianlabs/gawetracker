<?php

namespace App\Services\Discovery;

use Illuminate\Support\Facades\Http;

/**
 * Glints job source.
 *
 * Glints exposes a no-auth GraphQL endpoint that powers its own search page at
 * /api/v2-alc/graphql. We hit the `searchJobs` operation (the current one; the
 * older `searchJobsV3` still resolves but silently ignores the keyword) and
 * normalise each posting into a DiscoveredJob, so the discovery pipeline treats
 * Glints exactly like any other board.
 *
 * Two details verified against the live API and easy to get wrong:
 *  - the keyword field is `SearchTerm` and must be sent as a plain string;
 *  - pagination uses `limit`/`offset`, NOT `page`/`pageSize` (page is ignored).
 *
 * The schema is reverse-engineered and may drift; parsing is deliberately
 * defensive — a malformed item is skipped rather than aborting the whole search,
 * and a transport failure yields an empty result set instead of an exception.
 */
class GlintsSource implements JobSource
{
    public const NAME = 'glints';

    private const DEFAULT_ENDPOINT = 'https://glints.com/api/v2-alc/graphql';

    /**
     * The operation Glints' own search page issues.
     */
    private const OPERATION = 'searchJobs';

    /** Glints caps a single page at 30 results. */
    private const MAX_PAGE_SIZE = 30;

    /**
     * The exact operation Glints' own job-search page issues.
     */
    private const QUERY = <<<'GRAPHQL'
    query searchJobs($data: JobSearchConditionInput!) {
      searchJobs(data: $data) {
        jobsInPage {
          id
          title
          company {
            name
            brandName
          }
          city {
            name
          }
          country {
            code
            name
          }
          salaries {
            salaryType
            salaryMode
            maxAmount
            minAmount
            CurrencyCode
          }
          createdAt
        }
        totalJobs
      }
    }
    GRAPHQL;

    /**
     * A real Chrome User-Agent. Glints' firewall rejects obvious non-browser
     * clients, so requests must look like the search page itself.
     */
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

    /**
     * @param  string|null  $fallbackCompany  Company label used when a posting
     *                                        carries no company name.
     */
    public function __construct(private readonly ?string $fallbackCompany = null) {}

    public function name(): string
    {
        return self::NAME;
    }

    /**
     * Search Glints for a keyword and return up to $limit normalised postings.
     *
     * Never throws: an empty result, a malformed payload or a failed request all
     * produce an empty array.
     *
     * @return array<int, DiscoveredJob>
     */
    public function search(string $keyword, int $limit = 30): array
    {
        $limit = max(0, $limit);
        if ($limit === 0) {
            return [];
        }

        $endpoint = (string) config('services.glints.endpoint', self::DEFAULT_ENDPOINT);
        $country = (string) config('services.glints.country', 'ID');

        // Glints paginates by offset, so pull in batches until we have enough or
        // the board stops returning rows.
        $batch = min($limit, self::MAX_PAGE_SIZE);
        $jobs = [];
        $offset = 0;

        while (count($jobs) < $limit) {
            $result = $this->fetchPage($endpoint, $keyword, $country, $batch, $offset);

            if ($result === null) {
                break;
            }

            $items = $result['jobsInPage'] ?? null;
            if (! is_array($items) || $items === []) {
                break;
            }

            foreach ($items as $item) {
                $job = $this->mapItem($item, $endpoint);
                if ($job !== null) {
                    $jobs[] = $job;
                }

                if (count($jobs) >= $limit) {
                    break 2;
                }
            }

            // A short page means we have reached the end of the result set.
            if (count($items) < $batch) {
                break;
            }

            $offset += $batch;
        }

        return $jobs;
    }

    /**
     * Fetch one page of the searchJobs operation.
     *
     * @return array<string, mixed>|null the `searchJobs` object, or null on failure
     */
    private function fetchPage(string $endpoint, string $keyword, string $country, int $limit, int $offset): ?array
    {
        $response = Http::withHeaders([
            'User-Agent' => self::USER_AGENT,
            'Origin' => 'https://glints.com',
            'Referer' => 'https://glints.com/id/opportunities/jobs/explore',
            'Accept' => 'application/json',
        ])
            ->acceptJson()
            ->timeout(30)
            ->post($endpoint, [
                'operationName' => self::OPERATION,
                'query' => self::QUERY,
                'variables' => [
                    'data' => [
                        'SearchTerm' => $keyword,
                        'CountryCode' => $country,
                        'includeExternalJobs' => true,
                        'limit' => $limit,
                        'offset' => $offset,
                    ],
                ],
            ]);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json('data.searchJobs');

        return is_array($data) ? $data : null;
    }

    /**
     * Normalise a single GraphQL job item. Returns null when it is unusable
     * (missing id or title, or not an object at all).
     */
    private function mapItem(mixed $item, string $endpoint): ?DiscoveredJob
    {
        if (! is_array($item)) {
            return null;
        }

        $id = trim((string) ($item['id'] ?? ''));
        $title = trim((string) ($item['title'] ?? ''));

        if ($id === '' || $title === '') {
            return null;
        }

        $company = $this->firstString([
            $item['company']['name'] ?? null,
            $item['company']['brandName'] ?? null,
            $this->fallbackCompany,
        ]);

        return new DiscoveredJob(
            source: self::NAME,
            externalId: $id,
            title: $title,
            company: $company ?? '',
            location: $this->firstString([$item['city']['name'] ?? null]),
            sourceUrl: $this->baseUrl($endpoint).'/id/opportunities/jobs/'.$id,
            salaryNote: $this->formatSalary($item['salaries'] ?? null),
            postedAt: $this->firstString([$item['createdAt'] ?? null]),
        );
    }

    /**
     * Human-readable salary range from Glints' `salaries` array, e.g.
     * "IDR 10,000,000 - 15,000,000". Null when no amounts are advertised.
     */
    private function formatSalary(mixed $salaries): ?string
    {
        if (! is_array($salaries)) {
            return null;
        }

        foreach ($salaries as $salary) {
            if (! is_array($salary)) {
                continue;
            }

            $min = $this->toAmount($salary['minAmount'] ?? null);
            $max = $this->toAmount($salary['maxAmount'] ?? null);

            if ($min === null && $max === null) {
                continue;
            }

            $currency = trim((string) ($salary['CurrencyCode'] ?? ''));
            $label = $currency !== '' ? $currency.' ' : '';

            if ($min !== null && $max !== null) {
                return $label.number_format($min).' - '.number_format($max);
            }

            return $min !== null
                ? $label.'>= '.number_format($min)
                : $label.'<= '.number_format($max);
        }

        return null;
    }

    private function toAmount(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Scheme + host of the endpoint, used to build absolute posting URLs.
     */
    private function baseUrl(string $endpoint): string
    {
        $scheme = parse_url($endpoint, PHP_URL_SCHEME) ?: 'https';
        $host = parse_url($endpoint, PHP_URL_HOST) ?: 'glints.com';

        return $scheme.'://'.$host;
    }

    /**
     * @param  array<int, mixed>  $candidates
     */
    private function firstString(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }
}
