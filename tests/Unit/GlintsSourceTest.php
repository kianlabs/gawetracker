<?php

namespace Tests\Unit;

use App\Models\JobPosting;
use App\Services\Discovery\DiscoveredJob;
use App\Services\Discovery\GlintsSource;
use App\Services\Discovery\JobDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GlintsSourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.glints.endpoint', 'https://glints.com/api/v2-alc/graphql');
        config()->set('services.glints.country', 'ID');
    }

    /**
     * A single item shaped exactly like the searchJobs GraphQL node.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function item(array $overrides = []): array
    {
        return array_replace([
            'id' => '18ea50cf-5843-4455-936f-19fdfa2f98fd',
            'title' => 'Backend Engineer',
            'company' => ['name' => 'PT Tokopedia', 'brandName' => 'Tokopedia'],
            'city' => ['name' => 'Jakarta'],
            'country' => ['code' => 'ID', 'name' => 'Indonesia'],
            'salaries' => [[
                'salaryType' => 'MONTHLY',
                'salaryMode' => 'RANGE',
                'maxAmount' => 15000000,
                'minAmount' => 10000000,
                'CurrencyCode' => 'IDR',
            ]],
            'createdAt' => '2026-09-30T10:12:39Z',
        ], $overrides);
    }

    /**
     * Wrap items in the envelope Glints returns for the searchJobs operation.
     *
     * @param  array<int, mixed>  $items
     * @return array<string, mixed>
     */
    private function payload(array $items): array
    {
        return [
            'data' => [
                'searchJobs' => [
                    'jobsInPage' => $items,
                    'totalJobs' => count($items),
                ],
            ],
        ];
    }

    public function test_it_reports_its_source_name(): void
    {
        $this->assertSame('glints', (new GlintsSource)->name());
    }

    public function test_it_maps_a_graphql_item_to_a_discovered_job(): void
    {
        Http::fake(['glints.com/*' => Http::response($this->payload([$this->item()]))]);

        $jobs = (new GlintsSource)->search('backend');

        $this->assertCount(1, $jobs);
        $this->assertInstanceOf(DiscoveredJob::class, $jobs[0]);

        $job = $jobs[0];
        $this->assertSame('glints', $job->source);
        $this->assertSame('18ea50cf-5843-4455-936f-19fdfa2f98fd', $job->externalId);
        $this->assertSame('Backend Engineer', $job->title);
        $this->assertSame('PT Tokopedia', $job->company);
        $this->assertSame('Jakarta', $job->location);
        $this->assertSame('IDR 10,000,000 - 15,000,000', $job->salaryNote);
        $this->assertSame('2026-09-30T10:12:39Z', $job->postedAt);
    }

    public function test_it_builds_an_absolute_glints_url_from_the_job_id(): void
    {
        Http::fake(['glints.com/*' => Http::response($this->payload([$this->item()]))]);

        $job = (new GlintsSource)->search('backend')[0];

        $this->assertSame(
            'https://glints.com/id/opportunities/jobs/18ea50cf-5843-4455-936f-19fdfa2f98fd',
            $job->sourceUrl,
        );
    }

    public function test_it_sends_the_searchjobs_operation_with_the_keyword_as_a_string(): void
    {
        Http::fake(['glints.com/*' => Http::response($this->payload([$this->item()]))]);

        (new GlintsSource)->search('data analyst', limit: 10);

        Http::assertSent(function (Request $request) {
            $body = $request->data();

            return $request->url() === 'https://glints.com/api/v2-alc/graphql'
                && $request->method() === 'POST'
                && ($body['operationName'] ?? null) === 'searchJobs'
                && str_contains($body['query'] ?? '', 'searchJobs')
                && ! str_contains($body['query'] ?? '', 'searchJobsV3')
                // The keyword must be a plain string — the API ignores it otherwise.
                && ($body['variables']['data']['SearchTerm'] ?? null) === 'data analyst'
                && ($body['variables']['data']['CountryCode'] ?? null) === 'ID'
                // Glints paginates by limit/offset, not page/pageSize.
                && ($body['variables']['data']['limit'] ?? null) === 10
                && ($body['variables']['data']['offset'] ?? null) === 0
                && ($body['variables']['data']['includeExternalJobs'] ?? null) === true;
        });
    }

    public function test_it_sends_browser_like_headers(): void
    {
        Http::fake(['glints.com/*' => Http::response($this->payload([$this->item()]))]);

        (new GlintsSource)->search('backend');

        Http::assertSent(fn (Request $request) => str_contains($request->header('User-Agent')[0] ?? '', 'Mozilla')
            && ($request->header('Origin')[0] ?? null) === 'https://glints.com');
    }

    public function test_an_empty_result_set_returns_an_empty_array(): void
    {
        Http::fake(['glints.com/*' => Http::response($this->payload([]))]);

        $this->assertSame([], (new GlintsSource)->search('nonexistent-keyword'));
    }

    public function test_a_transport_failure_returns_an_empty_array_and_never_throws(): void
    {
        Http::fake(['glints.com/*' => Http::response('forbidden', 403)]);

        $this->assertSame([], (new GlintsSource)->search('backend'));
    }

    public function test_a_server_error_returns_an_empty_array(): void
    {
        Http::fake(['glints.com/*' => Http::response(['errors' => [['message' => 'boom']]], 500)]);

        $this->assertSame([], (new GlintsSource)->search('backend'));
    }

    public function test_a_malformed_payload_returns_an_empty_array(): void
    {
        Http::fake(['glints.com/*' => Http::response(['unexpected' => 'shape'])]);

        $this->assertSame([], (new GlintsSource)->search('backend'));
    }

    public function test_items_missing_an_id_or_title_are_skipped(): void
    {
        Http::fake(['glints.com/*' => Http::response($this->payload([
            $this->item(['id' => '']),
            $this->item(['id' => 'keep-me', 'title' => '']),
            $this->item(['id' => 'valid-1']),
            'not-an-object',
        ]))]);

        $jobs = (new GlintsSource)->search('backend');

        $this->assertCount(1, $jobs);
        $this->assertSame('valid-1', $jobs[0]->externalId);
    }

    public function test_it_honours_the_limit(): void
    {
        Http::fake(['glints.com/*' => Http::response($this->payload([
            $this->item(['id' => 'a']),
            $this->item(['id' => 'b']),
            $this->item(['id' => 'c']),
        ]))]);

        $jobs = (new GlintsSource)->search('backend', limit: 2);

        $this->assertCount(2, $jobs);
        $this->assertSame(['a', 'b'], array_map(fn (DiscoveredJob $j) => $j->externalId, $jobs));
    }

    public function test_a_zero_limit_short_circuits_without_any_request(): void
    {
        Http::fake();

        $this->assertSame([], (new GlintsSource)->search('backend', limit: 0));
        Http::assertNothingSent();
    }

    public function test_it_falls_back_to_brand_name_when_name_is_absent(): void
    {
        Http::fake(['glints.com/*' => Http::response($this->payload([
            $this->item(['company' => ['name' => '', 'brandName' => 'Tokopedia']]),
        ]))]);

        $this->assertSame('Tokopedia', (new GlintsSource)->search('backend')[0]->company);
    }

    public function test_it_uses_the_configured_fallback_company_when_glints_omits_it(): void
    {
        Http::fake(['glints.com/*' => Http::response($this->payload([
            $this->item(['company' => ['name' => '', 'brandName' => '']]),
        ]))]);

        $job = (new GlintsSource('Confidential Employer'))->search('backend')[0];

        $this->assertSame('Confidential Employer', $job->company);
    }

    public function test_a_missing_salary_or_city_is_null_not_an_error(): void
    {
        Http::fake(['glints.com/*' => Http::response($this->payload([
            $this->item(['salaries' => [], 'city' => ['name' => ''], 'createdAt' => null]),
        ]))]);

        $job = (new GlintsSource)->search('backend')[0];

        $this->assertNull($job->salaryNote);
        $this->assertNull($job->location);
        $this->assertNull($job->postedAt);
    }

    public function test_a_salary_with_only_a_minimum_is_formatted(): void
    {
        Http::fake(['glints.com/*' => Http::response($this->payload([
            $this->item(['salaries' => [['minAmount' => 8000000, 'maxAmount' => null, 'CurrencyCode' => 'IDR']]]),
        ]))]);

        $this->assertSame('IDR >= 8,000,000', (new GlintsSource)->search('backend')[0]->salaryNote);
    }

    public function test_it_paginates_by_offset_until_the_limit_is_reached(): void
    {
        // A limit above one page (30) forces a second request at offset 30.
        Http::fake([
            'glints.com/*' => function (Request $request) {
                $offset = $request->data()['variables']['data']['offset'] ?? 0;
                $count = $offset === 0 ? 30 : 2;

                $items = [];
                for ($i = 0; $i < $count; $i++) {
                    $items[] = $this->item(['id' => 'job-'.($offset + $i)]);
                }

                return Http::response($this->payload($items));
            },
        ]);

        $jobs = (new GlintsSource)->search('backend', limit: 32);

        $this->assertCount(32, $jobs);
        $this->assertSame('job-0', $jobs[0]->externalId);
        $this->assertSame('job-31', $jobs[31]->externalId);
        Http::assertSentCount(2);
    }

    public function test_it_stops_paginating_after_a_short_page(): void
    {
        // Fewer rows than requested means the end of the result set.
        Http::fake(['glints.com/*' => Http::response($this->payload([$this->item()]))]);

        (new GlintsSource)->search('backend', limit: 30);

        Http::assertSentCount(1);
    }

    public function test_discovered_postings_flow_through_the_discovery_pipeline(): void
    {
        $this->withoutExceptionHandling();
        Http::fake(['glints.com/*' => Http::response($this->payload([$this->item()]))]);

        $service = new JobDiscoveryService([new GlintsSource]);
        $result = $service->discover('backend');

        $this->assertSame(1, $result['created']);
        $this->assertSame([], $result['errors']);

        $posting = JobPosting::firstOrFail();
        $this->assertSame('glints', $posting->source);
        $this->assertSame('18ea50cf-5843-4455-936f-19fdfa2f98fd', $posting->external_id);
        $this->assertSame('Backend Engineer', $posting->title);
        $this->assertSame('PT Tokopedia', $posting->company);
        $this->assertNotNull($posting->company_id, 'The posting must link to a canonical company row.');
    }
}
