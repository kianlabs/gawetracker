<?php

namespace Tests\Feature;

use App\Models\JobApplication;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JobApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_job_application_with_defaults(): void
    {
        $application = JobApplication::create([
            'company' => 'Bukalapak',
            'position' => 'Backend Engineer',
            'applied_at' => '2026-09-30',
        ]);

        $this->assertDatabaseHas('job_applications', [
            'id' => $application->id,
            'company' => 'Bukalapak',
            'position' => 'Backend Engineer',
            'status' => 'wishlist',
        ]);

        $this->assertEquals('wishlist', $application->status);
    }

    public function test_job_application_has_many_status_histories_relationship(): void
    {
        $application = JobApplication::create([
            'company' => 'Tiket.com',
            'position' => 'Fullstack Engineer',
            'applied_at' => '2026-09-30',
            'status' => 'interview',
        ]);

        $history1 = $application->statusHistories()->create([
            'from_status' => 'wishlist',
            'to_status' => 'applied',
            'note' => 'Applied via website',
        ]);

        $history2 = $application->statusHistories()->create([
            'from_status' => 'applied',
            'to_status' => 'interview',
            'note' => 'Invited to interview',
        ]);

        $this->assertCount(2, $application->statusHistories);
        $this->assertTrue($history1->jobApplication->is($application));
        $this->assertTrue($history2->jobApplication->is($application));
    }

    public function test_deleting_job_application_cascades_to_status_histories(): void
    {
        $application = JobApplication::create([
            'company' => 'Blibli',
            'position' => 'Backend Engineer',
            'applied_at' => '2026-09-30',
        ]);

        $history = $application->statusHistories()->create([
            'from_status' => 'wishlist',
            'to_status' => 'applied',
        ]);

        $application->delete();

        $this->assertDatabaseMissing('job_applications', ['id' => $application->id]);
        $this->assertDatabaseMissing('status_histories', ['id' => $history->id]);
    }

    public function test_database_seeder_populates_user_and_job_applications(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('job_applications', 15);

        $user = User::first();
        $this->assertEquals('kyan@gawetracker.test', $user->email);
        $this->assertEquals('Kyan', $user->name);

        $applications = JobApplication::with('statusHistories')->get();
        $this->assertCount(15, $applications);

        foreach ($applications as $app) {
            $this->assertNotEmpty($app->company);
            $this->assertNotEmpty($app->position);
            $this->assertNotNull($app->applied_at);
            $this->assertGreaterThan(0, $app->statusHistories->count());

            // Seeding happens from the CLI with no session, so the owner must
            // be stamped explicitly. Orphaned rows would be invisible to the
            // account that logs in afterwards.
            $this->assertSame($user->id, $app->user_id);

            foreach ($app->statusHistories as $history) {
                $this->assertSame($user->id, $history->user_id);
            }
        }
    }

    public function test_database_seeder_is_idempotent(): void
    {
        // nixpacks runs db:seed on every deploy, so a second run must not
        // duplicate rows or histories.
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('job_applications', 15);

        $histories = DB::table('status_histories')->count();
        $this->assertGreaterThan(0, $histories);

        // Re-seeding once more must not change the history count either.
        $this->seed(DatabaseSeeder::class);
        $this->assertSame($histories, DB::table('status_histories')->count());
    }
}
