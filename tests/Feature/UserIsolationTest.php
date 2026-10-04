<?php

namespace Tests\Feature;

use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The security contract of a multi-user instance: one account must never see,
 * mutate, or delete another account's records. These tests are the guard rail
 * for the BelongsToUser global scope — if a query ever escapes it, these fail.
 */
class UserIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();
        $this->alice = User::factory()->create();
        $this->bob = User::factory()->create();
    }

    public function test_created_records_are_stamped_with_the_authenticated_user(): void
    {
        $this->actingAs($this->alice);

        $application = JobApplication::create([
            'company' => 'PT Milik Alice',
            'position' => 'Engineer',
            'applied_at' => now(),
        ]);

        $this->assertSame($this->alice->id, $application->user_id);
    }

    public function test_user_only_sees_their_own_applications_in_the_index(): void
    {
        $this->actingAs($this->alice);
        JobApplication::create(['company' => 'PT Alice', 'position' => 'Backend', 'applied_at' => now()]);

        $this->actingAs($this->bob);
        JobApplication::create(['company' => 'PT Bob', 'position' => 'Frontend', 'applied_at' => now()]);

        $response = $this->get('/applications');

        $response->assertOk();
        $response->assertSee('PT Bob');
        $response->assertDontSee('PT Alice');
    }

    public function test_user_cannot_view_another_users_application_detail(): void
    {
        $this->actingAs($this->alice);
        $application = JobApplication::create([
            'company' => 'PT Rahasia Alice',
            'position' => 'Engineer',
            'applied_at' => now(),
        ]);

        $this->actingAs($this->bob);
        $this->get("/applications/{$application->id}")->assertNotFound();
    }

    public function test_user_cannot_update_or_delete_another_users_application(): void
    {
        $this->actingAs($this->alice);
        $application = JobApplication::create([
            'company' => 'PT Rahasia Alice',
            'position' => 'Engineer',
            'applied_at' => now(),
        ]);

        $this->actingAs($this->bob);
        $this->put("/applications/{$application->id}", [
            'company' => 'Diretas',
            'position' => 'Hacked',
        ])->assertNotFound();
        $this->delete("/applications/{$application->id}")->assertNotFound();

        // The row survives untouched.
        $this->assertDatabaseHas('job_applications', [
            'id' => $application->id,
            'company' => 'PT Rahasia Alice',
        ]);
    }

    public function test_user_only_sees_their_own_discovered_postings(): void
    {
        $this->actingAs($this->alice);
        JobPosting::create([
            'source' => 'glints', 'external_id' => 'alice-1', 'title' => 'Lowongan Alice',
            'company' => 'PT Alice', 'location' => 'Jakarta', 'source_url' => 'https://x.test/a',
            'posted_at' => now(),
        ]);

        $this->actingAs($this->bob);
        JobPosting::create([
            'source' => 'glints', 'external_id' => 'bob-1', 'title' => 'Lowongan Bob',
            'company' => 'PT Bob', 'location' => 'Bandung', 'source_url' => 'https://x.test/b',
            'posted_at' => now(),
        ]);

        $response = $this->get(route('discovery.index'));

        $response->assertOk();
        $response->assertSee('Lowongan Bob');
        $response->assertDontSee('Lowongan Alice');
    }

    public function test_unauthenticated_queries_are_not_scoped(): void
    {
        // No actingAs(): the global scope stays dormant so console commands and
        // background jobs keep working across all users.
        $this->actingAs($this->alice);
        JobApplication::create(['company' => 'PT Alice', 'position' => 'Backend', 'applied_at' => now()]);
        $this->actingAs($this->bob);
        JobApplication::create(['company' => 'PT Bob', 'position' => 'Frontend', 'applied_at' => now()]);

        $this->app['auth']->logout();

        $this->assertSame(2, JobApplication::count());
    }
}
