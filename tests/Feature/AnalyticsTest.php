<?php

namespace Tests\Feature;

use App\Models\JobApplication;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/analytics');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_analytics_page(): void
    {
        $response = $this->actingAs($this->user)->get('/analytics');

        $response->assertStatus(200);
        $response->assertViewIs('analytics.index');
        $response->assertSee('Analisis Pipeline Rekrutmen');
        $response->assertSee('Di mana lamaran Anda gugur?');
        $response->assertSee('Distribusi Status Lamaran');
        $response->assertSee('Ringkasan Analisis');
    }

    public function test_active_count_reflects_current_status_not_cumulative_funnel(): void
    {
        // One application that passed through every funnel stage and ended in a
        // terminal state. The cumulative funnel counts it at each stage, so summing
        // the funnel would over-count — this is the regression guard.
        $app = JobApplication::create([
            'company' => 'PT Terminal',
            'position' => 'Engineer',
            'status' => 'rejected',
            'applied_at' => now()->subDays(30),
        ]);
        $app->statusHistories()->create(['from_status' => null, 'to_status' => 'wishlist', 'created_at' => now()->subDays(30)]);
        $app->statusHistories()->create(['from_status' => 'wishlist', 'to_status' => 'applied', 'created_at' => now()->subDays(28)]);
        $app->statusHistories()->create(['from_status' => 'applied', 'to_status' => 'screening', 'created_at' => now()->subDays(24)]);
        $app->statusHistories()->create(['from_status' => 'screening', 'to_status' => 'interview', 'created_at' => now()->subDays(18)]);
        $app->statusHistories()->create(['from_status' => 'interview', 'to_status' => 'offer', 'created_at' => now()->subDays(10)]);
        $app->statusHistories()->create(['from_status' => 'offer', 'to_status' => 'rejected', 'created_at' => now()->subDays(5)]);

        // 2 genuinely active applications (current status in the pipeline)
        JobApplication::create(['company' => 'PT Aktif 1', 'position' => 'Engineer', 'status' => 'applied', 'applied_at' => now()->subDays(3)]);
        JobApplication::create(['company' => 'PT Aktif 2', 'position' => 'Engineer', 'status' => 'interview', 'applied_at' => now()->subDays(2)]);

        // 1 hired (terminal)
        JobApplication::create(['company' => 'PT Hired', 'position' => 'Engineer', 'status' => 'hired', 'applied_at' => now()->subDays(20)]);

        $response = $this->actingAs($this->user)->get('/analytics');

        $response->assertStatus(200);
        $response->assertViewHas('totalApplications', 4);

        // Active = only the 2 with a current non-terminal status.
        $response->assertViewHas('activeCount', 2);

        // Sanity: active can never exceed the total number of applications.
        $activeCount = $response->viewData('activeCount');
        $this->assertLessThanOrEqual($response->viewData('totalApplications'), $activeCount);
    }

    public function test_active_count_is_zero_when_no_applications_exist(): void
    {
        $response = $this->actingAs($this->user)->get('/analytics');

        $response->assertStatus(200);
        $response->assertViewHas('activeCount', 0);
    }

    public function test_active_metric_matches_dashboard(): void
    {
        // Mix of every status, including terminal ones and a rejected app that
        // passed through several stages (would inflate a cumulative count).
        JobApplication::create(['company' => 'W', 'position' => 'E', 'status' => 'wishlist', 'applied_at' => now()->subDays(9)]);
        JobApplication::create(['company' => 'A', 'position' => 'E', 'status' => 'applied', 'applied_at' => now()->subDays(8)]);
        JobApplication::create(['company' => 'S', 'position' => 'E', 'status' => 'screening', 'applied_at' => now()->subDays(7)]);
        JobApplication::create(['company' => 'I', 'position' => 'E', 'status' => 'interview', 'applied_at' => now()->subDays(6)]);
        JobApplication::create(['company' => 'O', 'position' => 'E', 'status' => 'offer', 'applied_at' => now()->subDays(5)]);
        JobApplication::create(['company' => 'H', 'position' => 'E', 'status' => 'hired', 'applied_at' => now()->subDays(4)]);
        JobApplication::create(['company' => 'R', 'position' => 'E', 'status' => 'rejected', 'applied_at' => now()->subDays(3)]);

        $analytics = $this->actingAs($this->user)->get('/analytics');
        $dashboard = $this->actingAs($this->user)->get('/');

        // 5 active (wishlist, applied, screening, interview, offer) — hired/rejected excluded.
        $this->assertEquals(5, $analytics->viewData('activeCount'));
        $this->assertEquals(
            $dashboard->viewData('active'),
            $analytics->viewData('activeCount'),
            'Dashboard and analytics must report the same active count.'
        );
    }

    public function test_funnel_calculations_with_applications_at_different_stages(): void
    {
        // 1. Wishlist (never moved)
        JobApplication::create([
            'company' => 'PT Alpha',
            'position' => 'Backend Developer',
            'status' => 'wishlist',
            'applied_at' => now()->subDays(20),
        ]);

        // 2. Applied (started wishlist, moved to applied)
        $app2 = JobApplication::create([
            'company' => 'PT Beta',
            'position' => 'Frontend Developer',
            'status' => 'applied',
            'applied_at' => now()->subDays(15),
        ]);
        $app2->statusHistories()->create(['from_status' => null, 'to_status' => 'wishlist', 'created_at' => now()->subDays(16)]);
        $app2->statusHistories()->create(['from_status' => 'wishlist', 'to_status' => 'applied', 'created_at' => now()->subDays(15)]);

        // 3. Screening (wishlist -> applied -> screening)
        $app3 = JobApplication::create([
            'company' => 'PT Gamma',
            'position' => 'Fullstack Developer',
            'status' => 'screening',
            'applied_at' => now()->subDays(12),
        ]);
        $app3->statusHistories()->create(['from_status' => null, 'to_status' => 'wishlist', 'created_at' => now()->subDays(14)]);
        $app3->statusHistories()->create(['from_status' => 'wishlist', 'to_status' => 'applied', 'created_at' => now()->subDays(12)]);
        $app3->statusHistories()->create(['from_status' => 'applied', 'to_status' => 'screening', 'created_at' => now()->subDays(10)]);

        // 4. Interview (applied -> screening -> interview)
        $app4 = JobApplication::create([
            'company' => 'PT Delta',
            'position' => 'Mobile Developer',
            'status' => 'interview',
            'applied_at' => now()->subDays(10),
        ]);
        $app4->statusHistories()->create(['from_status' => null, 'to_status' => 'applied', 'created_at' => now()->subDays(10)]);
        $app4->statusHistories()->create(['from_status' => 'applied', 'to_status' => 'screening', 'created_at' => now()->subDays(8)]);
        $app4->statusHistories()->create(['from_status' => 'screening', 'to_status' => 'interview', 'created_at' => now()->subDays(5)]);

        // 5. Offer (applied -> screening -> interview -> offer)
        $app5 = JobApplication::create([
            'company' => 'PT Epsilon',
            'position' => 'DevOps Engineer',
            'status' => 'offer',
            'applied_at' => now()->subDays(25),
        ]);
        $app5->statusHistories()->create(['from_status' => null, 'to_status' => 'applied', 'created_at' => now()->subDays(25)]);
        $app5->statusHistories()->create(['from_status' => 'applied', 'to_status' => 'screening', 'created_at' => now()->subDays(22)]);
        $app5->statusHistories()->create(['from_status' => 'screening', 'to_status' => 'interview', 'created_at' => now()->subDays(18)]);
        $app5->statusHistories()->create(['from_status' => 'interview', 'to_status' => 'offer', 'created_at' => now()->subDays(10)]);

        // Total applications = 5
        // Reached wishlist: app1 (status), app2 (history), app3 (history) => 3
        // Reached applied: app2 (status), app3 (history), app4 (history), app5 (history) => 4
        // Reached screening: app3 (status), app4 (history), app5 (history) => 3
        // Reached interview: app4 (status), app5 (history) => 2
        // Reached offer: app5 (status) => 1
        // Reached hired: 0

        $response = $this->actingAs($this->user)->get('/analytics');

        $response->assertStatus(200);
        $response->assertViewHas('totalApplications', 5);

        $funnel = $response->viewData('funnel');
        $this->assertIsArray($funnel);

        $this->assertEquals(3, $funnel['wishlist']['count']);
        $this->assertEquals(60.0, $funnel['wishlist']['percentage']); // 3 / 5 * 100

        $this->assertEquals(4, $funnel['applied']['count']);
        $this->assertEquals(80.0, $funnel['applied']['percentage']); // 4 / 5 * 100

        $this->assertEquals(3, $funnel['screening']['count']);
        $this->assertEquals(60.0, $funnel['screening']['percentage']); // 3 / 5 * 100
        $this->assertEquals(75.0, $funnel['screening']['conversion_rate']); // 3 / 4 * 100

        $this->assertEquals(2, $funnel['interview']['count']);
        $this->assertEquals(40.0, $funnel['interview']['percentage']); // 2 / 5 * 100
        $this->assertEquals(66.7, $funnel['interview']['conversion_rate']); // 2 / 3 * 100

        $this->assertEquals(1, $funnel['offer']['count']);
        $this->assertEquals(20.0, $funnel['offer']['percentage']); // 1 / 5 * 100
        $this->assertEquals(50.0, $funnel['offer']['conversion_rate']); // 1 / 2 * 100

        $this->assertEquals(0, $funnel['hired']['count']);
        $this->assertEquals(0.0, $funnel['hired']['percentage']);
        $this->assertEquals(0.0, $funnel['hired']['conversion_rate']);
    }

    public function test_rejection_analysis_groups_by_from_status_and_calculates_percentages(): void
    {
        // 2 rejected from 'applied'
        for ($i = 1; $i <= 2; $i++) {
            $app = JobApplication::create([
                'company' => "Company Applied {$i}",
                'position' => 'Developer',
                'status' => 'rejected',
                'applied_at' => now()->subDays(10),
            ]);
            $app->statusHistories()->create([
                'from_status' => 'applied',
                'to_status' => 'rejected',
                'note' => 'Berkas tidak sesuai kriteria',
            ]);
        }

        // 1 rejected from 'screening'
        $appScreening = JobApplication::create([
            'company' => 'Company Screening 1',
            'position' => 'Developer',
            'status' => 'rejected',
            'applied_at' => now()->subDays(12),
        ]);
        $appScreening->statusHistories()->create([
            'from_status' => 'screening',
            'to_status' => 'rejected',
            'note' => 'Gugur saat screening CV/portfolio',
        ]);

        // 1 rejected from 'interview'
        $appInterview = JobApplication::create([
            'company' => 'Company Interview 1',
            'position' => 'Developer',
            'status' => 'rejected',
            'applied_at' => now()->subDays(15),
        ]);
        $appInterview->statusHistories()->create([
            'from_status' => 'interview',
            'to_status' => 'rejected',
            'note' => 'Gugur di interview user',
        ]);

        // Total rejected = 4
        // from applied = 2 (50.0%)
        // from screening = 1 (25.0%)
        // from interview = 1 (25.0%)
        // Primary rejection stage = 'applied'

        $response = $this->actingAs($this->user)->get('/analytics');

        $response->assertStatus(200);
        $response->assertViewHas('totalRejected', 4);

        $rejectionAnalysis = $response->viewData('rejectionAnalysis');
        $this->assertEquals(4, $rejectionAnalysis['total_rejected']);
        $this->assertEquals('applied', $rejectionAnalysis['primary_stage']);

        $breakdown = $rejectionAnalysis['breakdown'];
        $this->assertEquals(2, $breakdown['applied']['count']);
        $this->assertEquals(50.0, $breakdown['applied']['percentage']);

        $this->assertEquals(1, $breakdown['screening']['count']);
        $this->assertEquals(25.0, $breakdown['screening']['percentage']);

        $this->assertEquals(1, $breakdown['interview']['count']);
        $this->assertEquals(25.0, $breakdown['interview']['percentage']);

        // Assert actionable advice is geared towards applied/CV ATS
        $this->assertStringContainsString('ATS', $rejectionAnalysis['advice']['title'] . ' ' . $rejectionAnalysis['advice']['description']);
    }

    public function test_weekly_application_volume_for_past_eight_weeks(): void
    {
        $currentMonday = now()->startOfWeek();

        // 3 applications in current week
        for ($i = 0; $i < 3; $i++) {
            JobApplication::create([
                'company' => "Weekly Current {$i}",
                'position' => 'Engineer',
                'status' => 'applied',
                'applied_at' => $currentMonday->copy()->addDays($i)->toDateString(),
            ]);
        }

        // 6 applications 2 weeks ago
        $twoWeeksAgoMonday = now()->subWeeks(2)->startOfWeek();
        for ($i = 0; $i < 6; $i++) {
            JobApplication::create([
                'company' => "Weekly Two Weeks Ago {$i}",
                'position' => 'Engineer',
                'status' => 'applied',
                'applied_at' => $twoWeeksAgoMonday->copy()->addDays($i % 5)->toDateString(),
            ]);
        }

        // 1 application 7 weeks ago (oldest week in 8-week window)
        $sevenWeeksAgoMonday = now()->subWeeks(7)->startOfWeek();
        JobApplication::create([
            'company' => 'Weekly Seven Weeks Ago',
            'position' => 'Engineer',
            'status' => 'applied',
            'applied_at' => $sevenWeeksAgoMonday->copy()->toDateString(),
        ]);

        $response = $this->actingAs($this->user)->get('/analytics');

        $response->assertStatus(200);
        $weeklyVolume = $response->viewData('weeklyVolume');

        $this->assertIsArray($weeklyVolume);
        $this->assertCount(8, $weeklyVolume);

        // Week 0 is 7 weeks ago
        $this->assertEquals($sevenWeeksAgoMonday->toDateString(), $weeklyVolume[0]['start_date']);
        $this->assertEquals(1, $weeklyVolume[0]['count']);

        // Week 5 is 2 weeks ago (7 - 2 = 5)
        $this->assertEquals($twoWeeksAgoMonday->toDateString(), $weeklyVolume[5]['start_date']);
        $this->assertEquals(6, $weeklyVolume[5]['count']);

        // Week 7 is current week (7 - 0 = 7)
        $this->assertEquals($currentMonday->toDateString(), $weeklyVolume[7]['start_date']);
        $this->assertEquals(3, $weeklyVolume[7]['count']);
        $this->assertTrue($weeklyVolume[7]['is_current']);

        // Check label format "W{number} {Month}"
        foreach ($weeklyVolume as $week) {
            $this->assertArrayHasKey('start_date', $week);
            $this->assertArrayHasKey('end_date', $week);
            $this->assertArrayHasKey('label', $week);
            $this->assertArrayHasKey('count', $week);
            $this->assertMatchesRegularExpression('/^W\d \w+$/', $week['label']);
        }
    }

    public function test_time_to_response_and_duration_calculations(): void
    {
        $baseDate = Carbon::parse('2026-09-01');

        // Application 1: applied on 2026-09-01, screening on 2026-09-05 (4 days)
        $app1 = JobApplication::create([
            'company' => 'Speed Test 1',
            'position' => 'Engineer',
            'status' => 'screening',
            'applied_at' => $baseDate->toDateString(),
        ]);
        $app1->statusHistories()->create([
            'from_status' => 'applied',
            'to_status' => 'screening',
            'created_at' => $baseDate->copy()->addDays(4)->setTime(10, 0),
        ]);

        // Application 2: applied on 2026-09-01, rejected on 2026-09-07 (6 days)
        $app2 = JobApplication::create([
            'company' => 'Speed Test 2',
            'position' => 'Engineer',
            'status' => 'rejected',
            'applied_at' => $baseDate->toDateString(),
        ]);
        $app2->statusHistories()->create([
            'from_status' => 'applied',
            'to_status' => 'rejected',
            'created_at' => $baseDate->copy()->addDays(6)->setTime(14, 0),
        ]);

        // Application 3: applied on 2026-09-01, interview on 2026-09-09, offer on 2026-09-21 (20 days)
        $app3 = JobApplication::create([
            'company' => 'Speed Test 3',
            'position' => 'Engineer',
            'status' => 'offer',
            'applied_at' => $baseDate->toDateString(),
        ]);
        $app3->statusHistories()->create([
            'from_status' => 'applied',
            'to_status' => 'interview',
            'created_at' => $baseDate->copy()->addDays(8)->setTime(9, 0),
        ]);
        $app3->statusHistories()->create([
            'from_status' => 'interview',
            'to_status' => 'offer',
            'created_at' => $baseDate->copy()->addDays(20)->setTime(16, 0),
        ]);

        // First responses:
        // App 1: 4 days (to screening)
        // App 2: 6 days (to rejected)
        // App 3: 8 days (to interview)
        // Average first response = (4 + 6 + 8) / 3 = 6.0 days

        // Offers:
        // App 3: 20 days (to offer)
        // Average offer = 20.0 days

        $response = $this->actingAs($this->user)->get('/analytics');

        $response->assertStatus(200);
        $timeToResponse = $response->viewData('timeToResponse');

        $this->assertEquals(6.0, $timeToResponse['avg_first_response_days']);
        $this->assertEquals(20.0, $timeToResponse['avg_offer_days']);
    }

    public function test_empty_state_when_no_applications_exist(): void
    {
        $response = $this->actingAs($this->user)->get('/analytics');

        $response->assertStatus(200);
        $response->assertViewHas('totalApplications', 0);
        $response->assertViewHas('totalRejected', 0);
        $response->assertSee('Belum ada penolakan tercatat.');

        $funnel = $response->viewData('funnel');
        foreach ($funnel as $stage) {
            $this->assertEquals(0, $stage['count']);
            $this->assertEquals(0.0, $stage['percentage']);
            $this->assertEquals(0.0, $stage['conversion_rate']);
        }

        $timeToResponse = $response->viewData('timeToResponse');
        $this->assertEquals(0.0, $timeToResponse['avg_first_response_days']);
        $this->assertEquals(0.0, $timeToResponse['avg_offer_days']);
    }
}
