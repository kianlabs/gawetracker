<?php

namespace Tests\Feature;

use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The scheduled re-run of saved searches. HTTP is faked so the command's
 * orchestration and per-user scoping are exercised without touching a board.
 */
class DiscoverSavedSearchesCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Minimal Glints payload so discovery has something to persist.
     *
     * @return array<string, mixed>
     */
    private function glintsPayload(string $id = 'job-1'): array
    {
        return [
            'data' => [
                'searchJobs' => [
                    'jobsInPage' => [[
                        'id' => $id,
                        'title' => 'Backend Engineer',
                        'company' => ['name' => 'PT Tokopedia'],
                        'city' => ['name' => 'Jakarta'],
                        'salaries' => [],
                        'createdAt' => '2026-09-30T10:12:39Z',
                    ]],
                    'totalJobs' => 1,
                ],
            ],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.glints.endpoint', 'https://glints.com/api/v2-alc/graphql');
        config()->set('services.glints.country', 'ID');
        config()->set('services.jobstreet.endpoint', 'https://id.jobstreet.com/api/jobsearch/v5/search');

        Http::fake([
            'glints.com/*' => Http::response($this->glintsPayload()),
            'id.jobstreet.com/*' => Http::response(['data' => []], 200),
        ]);
    }

    public function test_it_runs_active_saved_searches_and_stamps_the_run(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        SavedSearch::create(['keyword' => 'backend', 'limit' => 10]);

        $this->artisan('jobs:discover-saved')->assertExitCode(0);

        $saved = SavedSearch::first();
        $this->assertNotNull($saved->last_run_at);
        $this->assertSame(1, $saved->last_created);
        $this->assertSame(1, $saved->last_seen);
        $this->assertSame(1, $user->jobPostings()->count());
    }

    public function test_it_skips_inactive_searches(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        SavedSearch::create(['keyword' => 'backend', 'limit' => 10, 'is_active' => false]);

        $this->artisan('jobs:discover-saved')->assertExitCode(0);

        $this->assertNull(SavedSearch::first()->last_run_at);
    }

    public function test_it_scopes_each_users_postings_to_that_user(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->actingAs($alice);
        SavedSearch::create(['keyword' => 'backend', 'limit' => 10]);
        $this->actingAs($bob);
        SavedSearch::create(['keyword' => 'frontend', 'limit' => 10]);

        $this->artisan('jobs:discover-saved')->assertExitCode(0);

        // The command leaves the last owner signed in; clear it so the
        // assertions below see every user's rows, not just that one.
        $this->app['auth']->logout();

        // Both searches hit the same fake job id, but each owner gets their own row.
        $this->assertSame(1, $alice->jobPostings()->count());
        $this->assertSame(1, $bob->jobPostings()->count());
    }

    public function test_user_option_limits_the_run_to_one_account(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->actingAs($alice);
        SavedSearch::create(['keyword' => 'backend', 'limit' => 10]);
        $this->actingAs($bob);
        SavedSearch::create(['keyword' => 'frontend', 'limit' => 10]);

        $this->artisan('jobs:discover-saved', ['--user' => $alice->email])->assertExitCode(0);

        $this->app['auth']->logout();

        $this->assertNotNull(SavedSearch::where('user_id', $alice->id)->first()->last_run_at);
        $this->assertNull(SavedSearch::where('user_id', $bob->id)->first()->last_run_at);
    }

    public function test_it_fails_when_no_users_exist(): void
    {
        $this->artisan('jobs:discover-saved')->assertExitCode(1);
    }

    public function test_it_fails_for_an_unknown_user(): void
    {
        User::factory()->create();

        $this->artisan('jobs:discover-saved', ['--user' => 'tidak-ada@example.test'])
            ->assertExitCode(1);
    }
}
