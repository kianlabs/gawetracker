<?php

namespace Tests\Feature;

use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JobDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function posting(array $overrides = []): JobPosting
    {
        return JobPosting::create(array_merge([
            'source' => 'glints',
            'external_id' => 'ext-'.uniqid(),
            'title' => 'Backend Engineer',
            'company' => 'PT Tokopedia',
            'location' => 'Jakarta',
            'source_url' => 'https://glints.com/id/opportunities/jobs/ext-1',
            'salary_note' => 'IDR 10,000,000 - 15,000,000',
            'posted_at' => now(),
        ], $overrides));
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('discovery.index'))->assertRedirect('/login');
        $this->post(route('discovery.search'), ['keyword' => 'backend'])->assertRedirect('/login');
    }

    public function test_index_lists_discovered_postings(): void
    {
        $this->posting(['title' => 'Backend Engineer', 'company' => 'Tokopedia']);
        $this->posting(['title' => 'Data Analyst', 'company' => 'Gojek', 'source' => 'jobstreet']);

        $response = $this->actingAs($this->user)->get(route('discovery.index'));

        $response->assertOk();
        $response->assertSee('Backend Engineer');
        $response->assertSee('Data Analyst');
        $response->assertSee('Tokopedia');
        $response->assertSee('Gojek');
    }

    public function test_index_filters_by_source(): void
    {
        $this->posting(['title' => 'Glints Only Job', 'source' => 'glints']);
        $this->posting(['title' => 'Jobstreet Only Job', 'source' => 'jobstreet']);

        $response = $this->actingAs($this->user)->get(route('discovery.index', ['source' => 'glints']));

        $response->assertOk();
        $response->assertSee('Glints Only Job');
        $response->assertDontSee('Jobstreet Only Job');
    }

    public function test_index_filters_by_search_term(): void
    {
        $this->posting(['title' => 'Backend Engineer', 'company' => 'Alpha']);
        $this->posting(['title' => 'Graphic Designer', 'company' => 'Beta']);

        $response = $this->actingAs($this->user)->get(route('discovery.index', ['search' => 'Backend']));

        $response->assertOk();
        $response->assertSee('Backend Engineer');
        $response->assertDontSee('Graphic Designer');
    }

    public function test_index_can_filter_to_unpromoted_only(): void
    {
        $this->posting(['title' => 'Fresh Job']);
        $this->posting(['title' => 'Taken Job', 'job_application_id' => JobApplication::create([
            'company' => 'X', 'position' => 'Y', 'applied_at' => now(), 'status' => 'wishlist',
        ])->id]);

        $response = $this->actingAs($this->user)->get(route('discovery.index', ['promoted' => 'no']));

        $response->assertOk();
        $response->assertSee('Fresh Job');
        $response->assertDontSee('Taken Job');
    }

    public function test_search_requires_a_keyword(): void
    {
        $response = $this->actingAs($this->user)
            ->from(route('discovery.index'))
            ->post(route('discovery.search'), ['keyword' => '']);

        $response->assertSessionHasErrors('keyword');
    }

    public function test_search_persists_results_from_both_boards(): void
    {
        Http::fake([
            'glints.com/*' => Http::response(['data' => ['searchJobs' => ['jobsInPage' => [[
                'id' => 'g-1', 'title' => 'Backend Engineer',
                'company' => ['name' => 'PT Tokopedia'],
                'city' => ['name' => 'Jakarta'],
            ]], 'totalJobs' => 1]]]),
            'id.jobstreet.com/*' => Http::response(['data' => [[
                'id' => 'j-1', 'title' => 'Backend Developer',
                'companyName' => 'Gojek', 'locations' => [['label' => 'Jakarta']],
            ]]]),
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('discovery.search'), ['keyword' => 'backend', 'limit' => 10]);

        $response->assertRedirect(route('discovery.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('job_postings', ['source' => 'glints', 'external_id' => 'g-1']);
        $this->assertDatabaseHas('job_postings', ['source' => 'jobstreet', 'external_id' => 'j-1']);
    }

    public function test_search_is_idempotent_on_re_run(): void
    {
        Http::fake(['glints.com/*' => Http::response(['data' => ['searchJobs' => ['jobsInPage' => [[
            'id' => 'g-1', 'title' => 'Backend Engineer', 'company' => ['name' => 'PT Tokopedia'],
        ]], 'totalJobs' => 1]]]),
            'id.jobstreet.com/*' => Http::response(['data' => []]),
        ]);

        $this->actingAs($this->user)->post(route('discovery.search'), ['keyword' => 'backend']);
        $this->actingAs($this->user)->post(route('discovery.search'), ['keyword' => 'backend']);

        $this->assertSame(1, JobPosting::where('external_id', 'g-1')->count());
    }

    public function test_promote_creates_an_application_and_links_the_posting(): void
    {
        $posting = $this->posting(['title' => 'Backend Engineer', 'company' => 'PT Tokopedia']);

        $response = $this->actingAs($this->user)->post(route('discovery.promote', $posting));

        $application = JobApplication::firstOrFail();
        $response->assertRedirect(route('applications.show', $application));

        $this->assertSame('Backend Engineer', $application->position);
        $this->assertSame('PT Tokopedia', $application->company);
        $this->assertSame('glints', $application->source);
        $this->assertSame('wishlist', $application->status);
        $this->assertNotNull($application->company_id, 'Promotion must link to a canonical company.');

        $this->assertSame($application->id, $posting->fresh()->job_application_id);
        $this->assertSame(1, $application->statusHistories()->count());
    }

    public function test_promoting_twice_does_not_create_a_duplicate_application(): void
    {
        $posting = $this->posting();

        $this->actingAs($this->user)->post(route('discovery.promote', $posting));
        $this->actingAs($this->user)->post(route('discovery.promote', $posting));

        $this->assertSame(1, JobApplication::count());
    }

    public function test_destroy_removes_a_posting(): void
    {
        $posting = $this->posting();

        $response = $this->actingAs($this->user)->delete(route('discovery.destroy', $posting));

        $response->assertRedirect(route('discovery.index'));
        $this->assertDatabaseMissing('job_postings', ['id' => $posting->id]);
    }
}
