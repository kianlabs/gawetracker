<?php

namespace Tests\Feature;

use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->app['auth']->logout();
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_sees_dashboard_with_correct_metrics(): void
    {
        // 1. Wishlist (active)
        JobApplication::create([
            'company' => 'Perusahaan A',
            'position' => 'Frontend Engineer',
            'status' => 'wishlist',
            'applied_at' => now()->subDays(15),
        ]);

        // 2. Applied (active)
        JobApplication::create([
            'company' => 'Perusahaan B',
            'position' => 'Backend Engineer',
            'status' => 'applied',
            'applied_at' => now()->subDays(10),
        ]);

        // 3. Screening (active)
        JobApplication::create([
            'company' => 'Perusahaan C',
            'position' => 'Fullstack Developer',
            'status' => 'screening',
            'applied_at' => now()->subDays(6),
        ]);

        // 4. Interview (active, reached interview)
        JobApplication::create([
            'company' => 'Perusahaan D',
            'position' => 'DevOps Engineer',
            'status' => 'interview',
            'applied_at' => now()->subDays(4),
        ]);

        // 5. Offer (inactive, reached interview)
        JobApplication::create([
            'company' => 'Perusahaan E',
            'position' => 'QA Engineer',
            'status' => 'offer',
            'applied_at' => now()->subDays(12),
        ]);

        // 6. Hired (inactive, reached interview)
        JobApplication::create([
            'company' => 'Perusahaan F',
            'position' => 'Lead Developer',
            'status' => 'hired',
            'applied_at' => now()->subDays(20),
        ]);

        // 7. Rejected, but reached interview via history (inactive, reached interview)
        $app7 = JobApplication::create([
            'company' => 'Perusahaan G',
            'position' => 'Engineering Manager',
            'status' => 'rejected',
            'applied_at' => now()->subDays(25),
        ]);
        $app7->statusHistories()->create([
            'from_status' => 'screening',
            'to_status' => 'interview',
            'note' => 'Undangan interview teknis',
        ]);
        $app7->statusHistories()->create([
            'from_status' => 'interview',
            'to_status' => 'rejected',
            'note' => 'Ditolak setelah interview',
        ]);

        // Total: 7
        // Active: 5 (A: wishlist, B: applied, C: screening, D: interview, E: offer)
        // Interview: 1 (D)
        // Offer: 1 (E)
        // Hired: 1 (F)
        // Rejected: 1 (G)
        // Reached interview: 4 (D, E, F, G)
        // Win rate: round((4 / 7) * 100, 1) = 57.1%

        $response = $this->actingAs($this->user)->get('/');

        $response->assertStatus(200);
        $response->assertViewIs('dashboard');
        $response->assertViewHas('total', 7);
        $response->assertViewHas('active', 5);
        $response->assertViewHas('interview', 1);
        $response->assertViewHas('offer', 1);
        $response->assertViewHas('hired', 1);
        $response->assertViewHas('rejected', 1);
        $response->assertViewHas('win_rate', 57.1);

        $response->assertSee('Total Lamaran');
        $response->assertSee('Aktif Diproses');
        $response->assertSee('Offering / Hired');
        $response->assertSee('Win Rate Interview');
        $response->assertSee('57.1%');
    }

    public function test_win_rate_is_zero_when_no_applications_exist(): void
    {
        $response = $this->actingAs($this->user)->get('/');

        $response->assertStatus(200);
        $response->assertViewHas('total', 0);
        $response->assertViewHas('win_rate', 0.0);
        $response->assertSee('0%');
    }

    public function test_weekly_goal_count_and_progress_bar(): void
    {
        // 2 applications submitted this week
        JobApplication::create([
            'company' => 'Minggu Ini 1',
            'position' => 'Backend Engineer',
            'applied_at' => now()->startOfWeek(),
        ]);
        JobApplication::create([
            'company' => 'Minggu Ini 2',
            'position' => 'Frontend Engineer',
            'applied_at' => now(),
        ]);

        // 1 application submitted 2 weeks ago
        JobApplication::create([
            'company' => 'Dua Minggu Lalu',
            'position' => 'Data Analyst',
            'applied_at' => now()->subWeeks(2),
        ]);

        // Default target is 8: count = 2, target = 8, percentage = round((2/8)*100) = 25%
        $response = $this->actingAs($this->user)->get('/');

        $response->assertStatus(200);
        $response->assertViewHas('weeklyCount', 2);
        $response->assertViewHas('weeklyTarget', 8);
        $response->assertViewHas('weeklyPercentage', 25);
        $response->assertSee('25%');
        $response->assertSee('Target Mingguan');
        $response->assertSee('2');
        $response->assertSee('8');

        // Custom target: ?target=4 -> percentage = round((2/4)*100) = 50%
        $responseCustom = $this->actingAs($this->user)->get('/?target=4');
        $responseCustom->assertStatus(200);
        $responseCustom->assertViewHas('weeklyCount', 2);
        $responseCustom->assertViewHas('weeklyTarget', 4);
        $responseCustom->assertViewHas('weeklyPercentage', 50);
        $responseCustom->assertSee('50%');

        // Target exceeded: count = 2, target = 1 -> capped at 100%
        $responseExceeded = $this->actingAs($this->user)->get('/?target=1');
        $responseExceeded->assertStatus(200);
        $responseExceeded->assertViewHas('weeklyCount', 2);
        $responseExceeded->assertViewHas('weeklyTarget', 1);
        $responseExceeded->assertViewHas('weeklyPercentage', 100);
        $responseExceeded->assertSee('Target tercapai!');
    }

    public function test_follow_up_reminder_displays_applications_over_seven_days_old(): void
    {
        // 1. Applied > 7 days ago with null last_status_change_at -> SHOULD appear (10 days)
        JobApplication::create([
            'company' => 'Perlu Followup 1',
            'position' => 'Go Engineer',
            'status' => 'applied',
            'applied_at' => now()->subDays(10),
            'last_status_change_at' => null,
        ]);

        // 2. Interview with last_status_change_at 9 days ago -> SHOULD appear (9 days)
        JobApplication::create([
            'company' => 'Perlu Followup 2',
            'position' => 'Ruby Engineer',
            'status' => 'interview',
            'applied_at' => now()->subDays(20),
            'last_status_change_at' => now()->subDays(9),
        ]);

        // 3. Screening updated 2 days ago -> SHOULD NOT appear (recently updated)
        JobApplication::create([
            'company' => 'Baru Screening',
            'position' => 'Python Engineer',
            'status' => 'screening',
            'applied_at' => now()->subDays(15),
            'last_status_change_at' => now()->subDays(2),
        ]);

        // 4. Applied 3 days ago -> SHOULD NOT appear (fresh)
        JobApplication::create([
            'company' => 'Baru Lamar',
            'position' => 'React Engineer',
            'status' => 'applied',
            'applied_at' => now()->subDays(3),
            'last_status_change_at' => null,
        ]);

        // 5. Rejected 14 days ago -> SHOULD NOT appear (terminal status)
        JobApplication::create([
            'company' => 'Sudah Ditolak',
            'position' => 'PHP Engineer',
            'status' => 'rejected',
            'applied_at' => now()->subDays(14),
            'last_status_change_at' => now()->subDays(10),
        ]);

        // 6. Hired 14 days ago -> SHOULD NOT appear (terminal status)
        JobApplication::create([
            'company' => 'Sudah Diterima',
            'position' => 'Staff Engineer',
            'status' => 'hired',
            'applied_at' => now()->subDays(14),
            'last_status_change_at' => now()->subDays(10),
        ]);

        // 7. Wishlist 20 days ago -> SHOULD NOT appear (wishlist not in reminders)
        JobApplication::create([
            'company' => 'Masih Wishlist',
            'position' => 'Product Engineer',
            'status' => 'wishlist',
            'applied_at' => now()->subDays(20),
            'last_status_change_at' => null,
        ]);

        $response = $this->actingAs($this->user)->get('/');

        $response->assertStatus(200);
        $response->assertViewHas('followUpApplications');

        $followUps = $response->viewData('followUpApplications');
        $this->assertCount(2, $followUps);

        $companies = $followUps->pluck('company')->all();
        $this->assertContains('Perlu Followup 1', $companies);
        $this->assertContains('Perlu Followup 2', $companies);
        $this->assertNotContains('Baru Screening', $companies);
        $this->assertNotContains('Baru Lamar', $companies);
        $this->assertNotContains('Sudah Ditolak', $companies);
        $this->assertNotContains('Sudah Diterima', $companies);
        $this->assertNotContains('Masih Wishlist', $companies);

        $response->assertSee('Perlu Followup 1');
        $response->assertSee('Perlu Followup 2');
        $response->assertSee('10 hari tanpa kabar');
        $response->assertSee('9 hari tanpa kabar');
    }

    public function test_empty_state_shown_when_no_applications_need_follow_up(): void
    {
        // Only fresh applications
        JobApplication::create([
            'company' => 'Fresh Company',
            'position' => 'Mobile Developer',
            'status' => 'applied',
            'applied_at' => now()->subDays(2),
        ]);

        $response = $this->actingAs($this->user)->get('/');

        $response->assertStatus(200);
        $response->assertSee('Tidak ada lamaran yang menggantung. Semua proses terkontrol!');
    }

    public function test_recent_applications_shows_up_to_five_ordered_by_applied_at_desc(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            JobApplication::create([
                'company' => "Company {$i}",
                'position' => "Position {$i}",
                'status' => 'applied',
                'applied_at' => now()->subDays(10 - $i),
            ]);
        }

        $response = $this->actingAs($this->user)->get('/');

        $response->assertStatus(200);
        $recent = $response->viewData('recentApplications');
        $this->assertCount(5, $recent);

        // Most recent should be Company 7 (subDays(3))
        $this->assertEquals('Company 7', $recent->first()->company);
    }

    public function test_weekly_target_persists_across_requests(): void
    {
        // Default when nothing saved yet
        $this->assertEquals(8, $this->user->weeklyTarget());

        // Save a new target
        $response = $this->actingAs($this->user)->post(route('dashboard.target.update'), [
            'target' => 12,
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('status');
        $this->assertEquals(12, $this->user->fresh()->weekly_target);

        // A fresh dashboard request (no query string) must show the saved value,
        // not the old hard-coded default.
        $dashboard = $this->actingAs($this->user)->get('/');
        $dashboard->assertViewHas('weeklyTarget', 12);
    }

    public function test_weekly_target_validation_rejects_out_of_range_values(): void
    {
        $this->actingAs($this->user)
            ->post(route('dashboard.target.update'), ['target' => 0])
            ->assertSessionHasErrors('target');

        $this->actingAs($this->user)
            ->post(route('dashboard.target.update'), ['target' => 101])
            ->assertSessionHasErrors('target');

        $this->actingAs($this->user)
            ->post(route('dashboard.target.update'), [])
            ->assertSessionHasErrors('target');

        // Nothing was persisted
        $this->assertEquals(8, $this->user->fresh()->weekly_target);
    }

    public function test_quick_status_change_from_dashboard_updates_status_and_clears_follow_up(): void
    {
        $app = JobApplication::create([
            'company' => 'PT Teknologi Maju',
            'position' => 'Senior Backend Engineer',
            'status' => 'applied',
            'applied_at' => now()->subDays(12),
            'last_status_change_at' => null,
        ]);

        // Initially appears in follow-up list on dashboard
        $response = $this->actingAs($this->user)->get('/');
        $response->assertSee('PT Teknologi Maju');
        $response->assertSee('12 hari tanpa kabar');

        // Submit quick status update to interview
        $postResponse = $this->actingAs($this->user)
            ->from('/')
            ->post(route('applications.quick-status', $app), [
                'status' => 'interview',
                'note' => 'Diundang user interview',
            ]);

        $postResponse->assertRedirect('/');
        $postResponse->assertSessionHas('success');

        $app->refresh();
        $this->assertEquals('interview', $app->status);
        $this->assertNotNull($app->last_status_change_at);
        $this->assertDatabaseHas('status_histories', [
            'job_application_id' => $app->id,
            'from_status' => 'applied',
            'to_status' => 'interview',
            'note' => 'Diundang user interview',
        ]);

        // Follow-up list should no longer include this app because it was updated just now (< 7 days ago)
        $followUpResponse = $this->actingAs($this->user)->get('/');
        $followUps = $followUpResponse->viewData('followUpApplications');
        $this->assertFalse($followUps->contains('id', $app->id));
    }
}
