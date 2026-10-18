<?php

namespace Tests\Feature;

use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobMatchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_unauthenticated_user_cannot_access_match_endpoints(): void
    {
        $application = JobApplication::create([
            'company' => 'Bukalapak',
            'position' => 'Fullstack Engineer',
            'applied_at' => '2026-10-01',
        ]);

        $this->post("/applications/{$application->id}/match")
            ->assertRedirect('/login');

        $this->post('/jobs/match-score', ['job_description' => 'Test'])
            ->assertRedirect('/login');
    }

    public function test_authenticated_user_can_match_application_via_form_post(): void
    {
        $application = JobApplication::create([
            'company' => 'Tokopedia',
            'position' => 'Senior Fullstack Developer',
            'notes' => 'Mencari kandidat ahli Laravel, React, TypeScript, MySQL, and Docker.',
            'applied_at' => '2026-10-01',
        ]);

        $response = $this->actingAs($this->user)
            ->from("/applications/{$application->id}")
            ->post("/applications/{$application->id}/match", [
                'job_description' => 'Kami membutuhkan Fullstack Developer dengan penguasaan Laravel, React, Next.js, MySQL, REST API, TypeScript, Docker, dan AWS.',
            ]);

        $response->assertRedirect("/applications/{$application->id}");
        $response->assertSessionHas('success');
        $response->assertSessionHas('match_result');

        $application->refresh();
        $this->assertNotNull($application->match_score);
        $this->assertGreaterThanOrEqual(75, $application->match_score);
        $this->assertIsArray($application->match_analysis);
        $this->assertContains('Laravel', $application->match_analysis['matched_keywords']);
        $this->assertContains('AWS', $application->match_analysis['missing_keywords']);
    }

    public function test_authenticated_user_can_match_application_via_json_request(): void
    {
        $application = JobApplication::create([
            'company' => 'Gojek',
            'position' => 'Fullstack Engineer',
            'notes' => 'Laravel and React position',
            'applied_at' => '2026-10-01',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/applications/{$application->id}/match", [
                'job_description' => 'Fullstack developer skilled in Laravel, MySQL, and REST API. Nice to have: Redis.',
            ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'match_score',
            'match_percentage',
            'matched_keywords',
            'missing_keywords',
            'recommendation',
            'analyzed_at',
        ]);

        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertContains('Laravel', $data['matched_keywords']);
        $this->assertContains('API', $data['matched_keywords']);
        $this->assertContains('Redis', $data['missing_keywords']);

        $application->refresh();
        $this->assertEquals($data['match_percentage'], $application->match_score);
    }

    public function test_standalone_match_endpoint_returns_json(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/jobs/match-score', [
                'job_description' => 'Looking for Fullstack Web Developer proficient in Next.js, React, TypeScript, and Docker.',
                'position' => 'Frontend / Fullstack Lead',
            ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'match_percentage',
            'matched_keywords',
            'missing_keywords',
            'recommendation',
        ]);

        $data = $response->json();
        $this->assertGreaterThanOrEqual(75, $data['match_percentage']);
        $this->assertContains('Next.js', $data['matched_keywords']);
        $this->assertContains('TypeScript', $data['matched_keywords']);
    }

    public function test_detail_view_shows_match_score_card_and_badges(): void
    {
        $application = JobApplication::create([
            'company' => 'Traveloka',
            'position' => 'Senior Fullstack Engineer',
            'applied_at' => '2026-10-01',
            'match_score' => 88,
            'match_analysis' => [
                'match_percentage' => 88,
                'matched_keywords' => ['Laravel', 'React', 'MySQL', 'API', 'Docker'],
                'missing_keywords' => ['AWS', 'Redis'],
                'recommendation' => 'Profil Anda sangat cocok dengan lowongan ini! Tekankan portofolio Laravel dan React.',
                'analyzed_at' => now()->toIso8601String(),
            ],
        ]);

        $response = $this->actingAs($this->user)->get("/applications/{$application->id}");

        $response->assertOk();
        $response->assertSee('AI Match Score &amp; ATS Keyword', false);
        $response->assertSee('88%');
        $response->assertSee('Laravel');
        $response->assertSee('React');
        $response->assertSee('AWS');
        $response->assertSee('Redis');
        $response->assertSee('Analisis Kesesuaian (Match Score)');
    }

    public function test_kanban_view_shows_match_score_badge(): void
    {
        $application = JobApplication::create([
            'company' => 'Ajaib',
            'position' => 'Web Developer',
            'status' => 'wishlist',
            'applied_at' => '2026-10-01',
            'match_score' => 92,
            'match_analysis' => [
                'match_percentage' => 92,
                'matched_keywords' => ['Laravel', 'React'],
                'missing_keywords' => [],
                'recommendation' => 'Sangat cocok!',
            ],
        ]);

        $response = $this->actingAs($this->user)->get('/applications/kanban');

        $response->assertOk();
        $response->assertSee('92% Match');
        $response->assertSee('kanbanMatchModal');
    }
}
