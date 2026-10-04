<?php

namespace Tests\Unit;

use App\Services\Discovery\DiscoveredJob;
use App\Services\Discovery\JobstreetSource;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JobstreetSourceTest extends TestCase
{
    /**
     * A realistic SEEK v5 result item, mirroring the live API response shape.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function item(array $overrides = []): array
    {
        return array_merge([
            'id' => '94704774',
            'title' => 'Backend Engineer',
            'advertiser' => ['id' => '63555831', 'description' => 'PT Tokopedia'],
            'companyName' => 'Tokopedia',
            'locations' => [['label' => 'South Jakarta, Jakarta', 'countryCode' => 'ID']],
            'listingDate' => '2026-09-23T09:17:16Z',
            'salaryLabel' => 'Rp 6.000.000 – Rp 7.500.000 per month',
            'workTypes' => ['Full time'],
        ], $overrides);
    }

    public function test_it_declares_the_jobstreet_source_name(): void
    {
        $this->assertSame('jobstreet', (new JobstreetSource)->name());
    }

    public function test_it_maps_a_v5_item_into_a_discovered_job(): void
    {
        $job = (new JobstreetSource)->parseItem($this->item());

        $this->assertInstanceOf(DiscoveredJob::class, $job);
        $this->assertSame('jobstreet', $job->source);
        $this->assertSame('94704774', $job->externalId);
        $this->assertSame('Backend Engineer', $job->title);
        $this->assertSame('PT Tokopedia', $job->company);
        $this->assertSame('South Jakarta, Jakarta', $job->location);
        $this->assertSame('https://id.jobstreet.com/id/job/94704774', $job->sourceUrl);
        $this->assertSame('Rp 6.000.000 – Rp 7.500.000 per month', $job->salaryNote);
        $this->assertSame('2026-09-23T09:17:16Z', $job->postedAt);
        $this->assertSame('jobstreet:94704774', $job->dedupKey());
    }

    public function test_it_falls_back_to_company_name_when_advertiser_description_is_blank(): void
    {
        $job = (new JobstreetSource)->parseItem($this->item([
            'advertiser' => ['id' => '1', 'description' => ''],
        ]));

        $this->assertSame('Tokopedia', $job->company);
    }

    public function test_it_rejects_items_without_an_id_or_title(): void
    {
        $source = new JobstreetSource;

        $this->assertNull($source->parseItem($this->item(['id' => ''])));
        $this->assertNull($source->parseItem($this->item(['title' => '  '])));
    }

    public function test_it_tolerates_missing_optional_fields(): void
    {
        $job = (new JobstreetSource)->parseItem([
            'id' => '42',
            'title' => 'Data Analyst',
        ]);

        $this->assertNotNull($job);
        $this->assertSame('', $job->company);
        $this->assertNull($job->location);
        $this->assertNull($job->salaryNote);
        $this->assertNull($job->postedAt);
        $this->assertSame('https://id.jobstreet.com/id/job/42', $job->sourceUrl);
    }

    public function test_it_builds_the_locale_prefixed_url_for_indonesian_hosts(): void
    {
        $source = new JobstreetSource('https://id.jobstreet.com/api/jobsearch/v5/search');

        $this->assertSame('https://id.jobstreet.com/id/job/123', $source->parseItem($this->item(['id' => '123']))->sourceUrl);
    }

    public function test_it_builds_a_bare_job_url_for_other_markets(): void
    {
        $source = new JobstreetSource('https://www.seek.com.au/api/jobsearch/v5/search', 'AU-Main');

        $this->assertSame('https://www.seek.com.au/job/123', $source->parseItem($this->item(['id' => '123']))->sourceUrl);
    }

    public function test_it_returns_no_url_for_an_untrusted_host(): void
    {
        $source = new JobstreetSource('https://evil.example.com/api/jobsearch/v5/search');

        $this->assertNull($source->parseItem($this->item())->sourceUrl);
    }

    public function test_it_queries_the_v5_endpoint_with_the_expected_parameters(): void
    {
        Http::fake([
            '*' => Http::response(['data' => [$this->item()], 'totalCount' => 1], 200),
        ]);

        $jobs = (new JobstreetSource)->search('backend', 10);

        $this->assertCount(1, $jobs);
        $this->assertInstanceOf(DiscoveredJob::class, $jobs[0]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/jobsearch/v5/search')
                && $request['siteKey'] === 'ID-Main'
                && $request['keywords'] === 'backend'
                && $request['page'] === 1
                && $request['pageSize'] === 10
                && $request->hasHeader('User-Agent');
        });
    }

    public function test_it_honours_the_configured_site_key(): void
    {
        Http::fake(['*' => Http::response(['data' => [$this->item()]], 200)]);

        (new JobstreetSource(null, 'SG-Main'))->search('engineer', 5);

        Http::assertSent(fn ($request) => $request['siteKey'] === 'SG-Main');
    }

    public function test_it_returns_an_empty_array_when_there_are_no_results(): void
    {
        Http::fake(['*' => Http::response(['data' => [], 'totalCount' => 0], 200)]);

        $this->assertSame([], (new JobstreetSource)->search('nonexistent-role-xyz', 10));
    }

    public function test_it_caps_a_single_request_at_the_seek_page_size(): void
    {
        Http::fake(['*' => Http::response(['data' => [], 'totalCount' => 0], 200)]);

        (new JobstreetSource)->search('engineer', 100);

        Http::assertSent(fn ($request) => (int) $request['pageSize'] === 30);
    }

    public function test_it_pages_until_the_limit_is_reached(): void
    {
        Http::fake([
            '*page=1*' => Http::response([
                'data' => array_map(
                    fn ($i) => $this->item(['id' => (string) $i]),
                    range(1, 30),
                ),
            ], 200),
            '*page=2*' => Http::response([
                'data' => array_map(
                    fn ($i) => $this->item(['id' => (string) $i]),
                    range(31, 40),
                ),
            ], 200),
        ]);

        $jobs = (new JobstreetSource)->search('engineer', 35);

        $this->assertCount(35, $jobs);
        $this->assertSame('1', $jobs[0]->externalId);
        $this->assertSame('35', $jobs[34]->externalId);
    }

    public function test_it_throws_when_the_api_returns_an_error_status(): void
    {
        Http::fake(['*' => Http::response('Forbidden', 403)]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Jobstreet API request failed (HTTP 403)');

        (new JobstreetSource)->search('backend', 10);
    }
}
