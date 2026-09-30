<?php

namespace Tests\Feature;

use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobApplicationCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $application = JobApplication::create([
            'company' => 'Bukalapak',
            'position' => 'Backend Engineer',
            'applied_at' => '2026-09-30',
        ]);

        $this->get('/applications')->assertRedirect('/login');
        $this->get('/applications/create')->assertRedirect('/login');
        $this->post('/applications', [])->assertRedirect('/login');
        $this->get("/applications/{$application->id}/edit")->assertRedirect('/login');
        $this->put("/applications/{$application->id}", [])->assertRedirect('/login');
        $this->delete("/applications/{$application->id}")->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_applications_index(): void
    {
        JobApplication::create([
            'company' => 'Tiket.com',
            'position' => 'Software Engineer',
            'applied_at' => '2026-09-20',
            'status' => 'interview',
        ]);

        $response = $this->actingAs($this->user)->get('/applications');

        $response->assertStatus(200);
        $response->assertSee('Daftar Lamaran Kerja');
        $response->assertSee('Tiket.com');
        $response->assertSee('Software Engineer');
        $response->assertSee('Interview');
    }

    public function test_applications_index_can_search_by_company_or_position(): void
    {
        JobApplication::create([
            'company' => 'Tokopedia',
            'position' => 'Backend Engineer',
            'applied_at' => '2026-09-20',
        ]);

        JobApplication::create([
            'company' => 'Blibli',
            'position' => 'Frontend Engineer',
            'applied_at' => '2026-09-21',
        ]);

        $response = $this->actingAs($this->user)->get('/applications?search=Tokopedia');

        $response->assertStatus(200);
        $response->assertSee('Tokopedia');
        $response->assertDontSee('Blibli');
    }

    public function test_applications_index_can_filter_by_status(): void
    {
        JobApplication::create([
            'company' => 'Company A',
            'position' => 'Dev A',
            'applied_at' => '2026-09-20',
            'status' => 'interview',
        ]);

        JobApplication::create([
            'company' => 'Company B',
            'position' => 'Dev B',
            'applied_at' => '2026-09-21',
            'status' => 'rejected',
        ]);

        $response = $this->actingAs($this->user)->get('/applications?status=interview');

        $response->assertStatus(200);
        $response->assertSee('Company A');
        $response->assertDontSee('Company B');
    }

    public function test_authenticated_user_can_view_create_form(): void
    {
        $response = $this->actingAs($this->user)->get('/applications/create');

        $response->assertStatus(200);
        $response->assertSee('Tambah Lamaran Kerja Baru');
        $response->assertSee('Nama Perusahaan');
        $response->assertSee('Posisi Pekerjaan');
    }

    public function test_authenticated_user_can_create_job_application_and_creates_initial_history(): void
    {
        $data = [
            'company' => 'Gojek',
            'position' => 'Senior Backend Developer',
            'location' => 'Jakarta Selatan',
            'work_type' => 'hybrid',
            'source' => 'LinkedIn',
            'source_url' => 'https://linkedin.com/jobs/view/123456',
            'applied_at' => '2026-09-30',
            'salary_note' => 'Rp 30.000.000',
            'contact_name' => 'Recruiter Team',
            'contact_info' => 'talent@gojek.com',
            'notes' => 'Referral dari teman kantor lama.',
            'status' => 'wishlist',
        ];

        $response = $this->actingAs($this->user)->post('/applications', $data);

        $response->assertRedirect('/applications');
        $response->assertSessionHas('success', 'Lamaran pekerjaan berhasil ditambahkan.');

        $this->assertDatabaseHas('job_applications', [
            'company' => 'Gojek',
            'position' => 'Senior Backend Developer',
            'status' => 'wishlist',
        ]);

        $application = JobApplication::where('company', 'Gojek')->first();
        $this->assertNotNull($application);
        $this->assertCount(1, $application->statusHistories);

        $history = $application->statusHistories->first();
        $this->assertNull($history->from_status);
        $this->assertEquals('wishlist', $history->to_status);
        $this->assertEquals('Lamaran dibuat.', $history->note);
    }

    public function test_store_validation_errors_are_in_indonesian(): void
    {
        $response = $this->actingAs($this->user)->post('/applications', [
            'company' => '',
            'position' => '',
            'applied_at' => 'not-a-date',
            'status' => 'invalid-status',
        ]);

        $response->assertSessionHasErrors([
            'company' => 'Nama perusahaan wajib diisi.',
            'position' => 'Posisi pekerjaan wajib diisi.',
            'applied_at' => 'Format tanggal melamar tidak valid.',
            'status' => 'Status yang dipilih tidak valid.',
        ]);
    }

    public function test_authenticated_user_can_view_edit_form(): void
    {
        $application = JobApplication::create([
            'company' => 'Shopee',
            'position' => 'QA Engineer',
            'applied_at' => '2026-09-25',
            'status' => 'applied',
        ]);

        $response = $this->actingAs($this->user)->get("/applications/{$application->id}/edit");

        $response->assertStatus(200);
        $response->assertSee('Edit Lamaran Kerja');
        $response->assertSee('Shopee');
        $response->assertSee('QA Engineer');
    }

    public function test_user_can_update_application_and_creates_status_history_when_status_changes(): void
    {
        $application = JobApplication::create([
            'company' => 'Traveloka',
            'position' => 'Backend Engineer',
            'applied_at' => '2026-09-10',
            'status' => 'applied',
        ]);

        $updateData = [
            'company' => 'Traveloka Indonesia',
            'position' => 'Senior Backend Engineer',
            'applied_at' => '2026-09-10',
            'status' => 'screening',
            'status_change_note' => 'Dihubungi HR via telepon untuk screening call.',
        ];

        $response = $this->actingAs($this->user)->put("/applications/{$application->id}", $updateData);

        $response->assertRedirect('/applications');
        $response->assertSessionHas('success', 'Lamaran pekerjaan berhasil diperbarui.');

        $application->refresh();
        $this->assertEquals('Traveloka Indonesia', $application->company);
        $this->assertEquals('Senior Backend Engineer', $application->position);
        $this->assertEquals('screening', $application->status);

        $this->assertCount(1, $application->statusHistories);
        $history = $application->statusHistories->first();
        $this->assertEquals('applied', $history->from_status);
        $this->assertEquals('screening', $history->to_status);
        $this->assertEquals('Dihubungi HR via telepon untuk screening call.', $history->note);
    }

    public function test_user_can_update_application_without_status_change_does_not_create_duplicate_history(): void
    {
        $application = JobApplication::create([
            'company' => 'Dana',
            'position' => 'Backend Engineer',
            'applied_at' => '2026-09-10',
            'status' => 'applied',
        ]);

        $updateData = [
            'company' => 'DANA Indonesia',
            'position' => 'Backend Engineer (Java)',
            'applied_at' => '2026-09-10',
            'status' => 'applied',
        ];

        $response = $this->actingAs($this->user)->put("/applications/{$application->id}", $updateData);

        $response->assertRedirect('/applications');

        $application->refresh();
        $this->assertEquals('DANA Indonesia', $application->company);
        $this->assertCount(0, $application->statusHistories);
    }

    public function test_user_can_delete_application(): void
    {
        $application = JobApplication::create([
            'company' => 'Midtrans',
            'position' => 'DevOps Engineer',
            'applied_at' => '2026-09-10',
        ]);

        $application->statusHistories()->create([
            'from_status' => null,
            'to_status' => 'wishlist',
            'note' => 'Initial',
        ]);

        $response = $this->actingAs($this->user)->delete("/applications/{$application->id}");

        $response->assertRedirect('/applications');
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('job_applications', ['id' => $application->id]);
        $this->assertDatabaseMissing('status_histories', ['job_application_id' => $application->id]);
    }
}
