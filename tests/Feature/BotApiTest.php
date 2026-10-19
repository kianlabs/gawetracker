<?php

namespace Tests\Feature;

use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_status_requires_authentication(): void
    {
        $response = $this->getJson('/api/bot/status');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                ],
            ]);
    }

    public function test_api_status_succeeds_with_valid_api_key(): void
    {
        $user = User::factory()->create();
        $token = $user->generateApiToken();

        $response = $this->withHeader('X-API-Key', $token)
            ->getJson('/api/bot/status');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'status' => 'online',
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                ],
            ]);
    }

    public function test_can_ingest_single_job_via_bot_lempar_jaring_payload(): void
    {
        $user = User::factory()->create();
        $token = $user->generateApiToken();

        $payload = [
            'company' => 'PT Tokopedia',
            'title' => 'Software Engineer - Backend',
            'platform' => 'Glints',
            'url' => 'https://glints.com/id/opportunities/jobs/se-backend',
            'location' => 'Jakarta (Hybrid)',
            'type' => 'hybrid',
            'salary' => 'Rp 15.000.000 - Rp 20.000.000',
            'status' => 'applied',
            'notes' => 'Screening otomatis terjawab via Gemini 2.0',
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/bot/applications', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'inserted_count' => 1,
                'skipped_count' => 0,
            ]);

        $this->assertDatabaseHas('job_applications', [
            'user_id' => $user->id,
            'company' => 'PT Tokopedia',
            'position' => 'Software Engineer - Backend',
            'work_type' => 'hybrid',
            'source' => 'LemparGawe Bot (Glints)',
            'source_url' => 'https://glints.com/id/opportunities/jobs/se-backend',
            'salary_note' => 'Rp 15.000.000 - Rp 20.000.000',
            'status' => 'applied',
        ]);

        $app = JobApplication::where('company', 'PT Tokopedia')->first();
        $this->assertNotNull($app);
        $this->assertCount(1, $app->statusHistories);
        $this->assertStringContainsString('Di-submit otomatis via LemparGawe Bot (Glints)', $app->statusHistories->first()->note);
    }

    public function test_can_ingest_batch_of_jobs(): void
    {
        $user = User::factory()->create();
        $token = $user->generateApiToken();

        $batchPayload = [
            'bot_name' => 'Bot Lempar Jaring',
            'jobs' => [
                [
                    'company' => 'Bukalapak',
                    'position' => 'DevOps Engineer',
                    'platform' => 'JobStreet',
                    'work_type' => 'remote',
                ],
                [
                    'company' => 'Traveloka',
                    'position' => 'Data Analyst',
                    'platform' => 'LinkedIn',
                    'work_type' => 'onsite',
                ],
            ],
        ];

        $response = $this->withHeader('X-API-Key', $token)
            ->postJson('/api/bot/applications', $batchPayload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'inserted_count' => 2,
                'skipped_count' => 0,
            ]);

        $this->assertDatabaseHas('job_applications', [
            'user_id' => $user->id,
            'company' => 'Bukalapak',
            'position' => 'DevOps Engineer',
        ]);

        $this->assertDatabaseHas('job_applications', [
            'user_id' => $user->id,
            'company' => 'Traveloka',
            'position' => 'Data Analyst',
        ]);
    }

    public function test_can_ingest_with_custom_bot_name_for_backward_compatibility(): void
    {
        $user = User::factory()->create();
        $token = $user->generateApiToken();

        $payload = [
            'company' => 'Gojek',
            'position' => 'Senior Go Developer',
            'platform' => 'JobStreet',
            'bot_name' => 'Bot-Lempar-Jaring-Loker',
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/bot/applications', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('job_applications', [
            'company' => 'Gojek',
            'source' => 'Bot-Lempar-Jaring-Loker (JobStreet)',
        ]);
    }

    public function test_skips_duplicate_application_within_window(): void
    {
        $user = User::factory()->create();
        $token = $user->generateApiToken();

        // First application
        JobApplication::create([
            'user_id' => $user->id,
            'company' => 'Shopee',
            'position' => 'QA Engineer',
            'applied_at' => now()->toDateString(),
            'status' => 'applied',
        ]);

        // Attempt to ingest same job via bot
        $response = $this->withHeader('X-API-Key', $token)
            ->postJson('/api/bot/applications', [
                'company' => 'Shopee',
                'title' => 'QA Engineer',
                'platform' => 'Glints',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'inserted_count' => 0,
                'skipped_count' => 1,
            ]);

        $this->assertEquals(1, JobApplication::where('company', 'Shopee')->count());
    }

    public function test_integrations_page_accessible_by_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/integrations');

        $response->assertOk()
            ->assertSee('Integrasi Bot &amp; Webhook', false)
            ->assertSee('/api/bot/applications');

        $this->assertNotNull($user->fresh()->api_token);
    }

    public function test_user_can_regenerate_api_token(): void
    {
        $user = User::factory()->create();
        $oldToken = $user->generateApiToken();

        $response = $this->actingAs($user)->post('/integrations/token/regenerate');

        $response->assertRedirect('/integrations');
        $this->assertNotEquals($oldToken, $user->fresh()->api_token);
    }

    public function test_user_can_update_bot_config(): void
    {
        $user = User::factory()->create();

        \Illuminate\Support\Facades\Http::fake([
            'http://localhost:3000/api/config' => \Illuminate\Support\Facades\Http::response(['success' => true], 200),
        ]);

        $response = $this->actingAs($user)->post('/integrations/bot/config', [
            'searchKeywords' => 'Senior Laravel Developer',
            'location' => 'Jakarta (Remote)',
            'minSalary' => 15000000,
            'limitPerDay' => 30,
            'enableGlints' => '1',
            'enableJobstreet' => '1',
        ]);

        $response->assertRedirect('/integrations');
        $response->assertSessionHas('success');
    }
}
