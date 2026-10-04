<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\JobPosting;
use App\Models\User;
use App\Services\Discovery\DiscoveredJob;
use App\Services\Discovery\JobDiscoveryService;
use App\Services\Discovery\JobSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A controllable in-memory source so the discovery pipeline is tested without
 * touching any real board.
 */
class FakeJobSource implements JobSource
{
    /** @param array<int, DiscoveredJob> $jobs */
    public function __construct(
        private readonly string $label,
        private readonly array $jobs = [],
        private readonly ?string $failWith = null,
    ) {}

    public function name(): string
    {
        return $this->label;
    }

    public function search(string $keyword, int $limit = 30): array
    {
        if ($this->failWith !== null) {
            throw new \RuntimeException($this->failWith);
        }

        return array_slice($this->jobs, 0, $limit);
    }
}

class JobDiscoveryServiceTest extends TestCase
{
    use RefreshDatabase;

    private function job(string $id, string $company, string $title = 'Backend Engineer'): DiscoveredJob
    {
        return new DiscoveredJob(
            source: 'glints',
            externalId: $id,
            title: $title,
            company: $company,
            location: 'Jakarta',
            sourceUrl: "https://glints.com/id/opportunities/jobs/{$id}",
            postedAt: '2026-09-30T10:12:39Z',
        );
    }

    public function test_it_persists_discovered_postings(): void
    {
        $service = new JobDiscoveryService([
            new FakeJobSource('glints', [$this->job('1', 'Tokopedia')]),
            new FakeJobSource('jobstreet', [
                new DiscoveredJob('jobstreet', '99', 'Data Analyst', 'Traveloka', 'Bandung'),
            ]),
        ]);

        $result = $service->discover('engineer');

        $this->assertSame(2, $result['created']);
        $this->assertSame(2, $result['seen']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(2, JobPosting::count());
    }

    public function test_re_running_is_idempotent(): void
    {
        $source = new FakeJobSource('glints', [$this->job('1', 'Tokopedia')]);

        (new JobDiscoveryService([$source]))->discover('engineer');
        $second = (new JobDiscoveryService([$source]))->discover('engineer');

        $this->assertSame(0, $second['created']);
        $this->assertSame(1, $second['updated']);
        $this->assertSame(1, JobPosting::count());
    }

    public function test_company_spellings_share_one_canonical_row(): void
    {
        $service = new JobDiscoveryService([
            new FakeJobSource('glints', [$this->job('1', 'PT Tokopedia')]),
            new FakeJobSource('jobstreet', [$this->job('2', 'Tokopedia, PT')]),
        ]);

        $service->discover('engineer');

        $this->assertSame(2, JobPosting::count());
        $this->assertSame(1, Company::count());
        $this->assertSame(
            JobPosting::first()->company_id,
            JobPosting::latest('id')->first()->company_id,
        );
    }

    public function test_one_failing_source_does_not_abort_the_others(): void
    {
        $service = new JobDiscoveryService([
            new FakeJobSource('glints', failWith: 'firewall blocked'),
            new FakeJobSource('jobstreet', [$this->job('5', 'Bukalapak')]),
        ]);

        $result = $service->discover('engineer');

        $this->assertSame(1, $result['created']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('glints', $result['errors'][0]);
        $this->assertSame(1, JobPosting::count());
    }

    public function test_it_parses_salary_note_into_structured_columns(): void
    {
        $job = new DiscoveredJob(
            source: 'jobstreet',
            externalId: '42',
            title: 'Backend Engineer',
            company: 'Tokopedia',
            location: 'Jakarta',
            sourceUrl: 'https://id.jobstreet.com/id/job/42',
            salaryNote: 'Rp 6.000.000 – Rp 7.500.000 per month',
            postedAt: '2026-09-30T10:12:39Z',
        );

        (new JobDiscoveryService([new FakeJobSource('jobstreet', [$job])]))->discover('engineer');

        $posting = JobPosting::first();

        $this->assertSame(6_000_000, $posting->salary_min);
        $this->assertSame(7_500_000, $posting->salary_max);
        $this->assertSame('IDR', $posting->salary_currency);
        $this->assertSame('monthly', $posting->salary_period);
        $this->assertSame('Rp 6.000.000 – Rp 7.500.000 per month', $posting->salary_note);
        $this->assertSame('IDR 6.000.000 – 7.500.000 /bulan', $posting->salaryLabel());
    }

    public function test_unparseable_salary_note_leaves_columns_null(): void
    {
        $job = new DiscoveredJob(
            source: 'glints',
            externalId: '43',
            title: 'Designer',
            company: 'Tokopedia',
            salaryNote: 'Kompetitif',
        );

        (new JobDiscoveryService([new FakeJobSource('glints', [$job])]))->discover('designer');

        $posting = JobPosting::first();

        $this->assertNull($posting->salary_min);
        $this->assertNull($posting->salary_max);
        $this->assertNull($posting->salary_currency);
        // The label accessor still shows the board's original prose.
        $this->assertSame('Kompetitif', $posting->salaryLabel());
    }

    public function test_persist_recovers_when_a_concurrent_insert_wins_the_race(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $armed = true;

        // Reproduce the race window: another worker persists the same posting
        // after our lookup but before our insert. The unique
        // (user_id, source, external_id) index then rejects the loser.
        JobPosting::creating(function (JobPosting $model) use (&$armed, $user): void {
            if (! $armed || $model->source !== 'glints' || $model->external_id !== 'race-1') {
                return;
            }

            $armed = false;

            DB::table('job_postings')->insert([
                'user_id' => $user->id,
                'source' => 'glints',
                'external_id' => 'race-1',
                'title' => 'Concurrent Winner',
                'company' => 'Tokopedia',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $service = new JobDiscoveryService([]);
        $isNew = $service->persist($this->job('race-1', 'Tokopedia'));

        $this->assertFalse($isNew, 'A lost race means the posting already existed.');
        $this->assertSame(1, JobPosting::where('source', 'glints')->where('external_id', 'race-1')->count());
    }

    public function test_posting_links_to_a_promoted_application(): void
    {
        $service = new JobDiscoveryService([new FakeJobSource('glints', [$this->job('7', 'Gojek')])]);
        $service->discover('engineer');

        $posting = JobPosting::first();

        $this->assertFalse($posting->isPromoted());

        $app = $posting->companyRecord->jobApplications()->create([
            'company' => $posting->company,
            'company_id' => $posting->company_id,
            'position' => $posting->title,
            'applied_at' => now()->toDateString(),
            'status' => 'applied',
        ]);

        $posting->update(['job_application_id' => $app->id]);

        $this->assertTrue($posting->fresh()->isPromoted());
        $this->assertSame($app->id, $posting->fresh()->jobApplication->id);
    }
}
