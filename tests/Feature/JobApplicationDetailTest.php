<?php

namespace Tests\Feature;

use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobApplicationDetailTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_unauthenticated_user_cannot_access_detail_or_actions(): void
    {
        $this->app['auth']->logout();
        $application = JobApplication::create([
            'company' => 'Tokopedia',
            'position' => 'Software Engineer',
            'applied_at' => '2026-09-30',
        ]);

        $this->get("/applications/{$application->id}")->assertRedirect('/login');
        $this->post("/applications/{$application->id}/quick-status", ['status' => 'screening'])->assertRedirect('/login');
        $this->post("/applications/{$application->id}/histories", ['note' => 'Catatan'])->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_application_detail_page(): void
    {
        $application = JobApplication::create([
            'company' => 'Traveloka Indonesia',
            'position' => 'Fullstack Developer',
            'location' => 'BSD City, Tangerang',
            'work_type' => 'hybrid',
            'source' => 'LinkedIn Jobs',
            'source_url' => 'https://jobs.traveloka.com/positions/fullstack',
            'applied_at' => '2026-09-15',
            'salary_note' => 'Rp 25.000.000 - Rp 30.000.000',
            'contact_name' => 'Budi Pratama',
            'contact_info' => 'budi.hr@traveloka.com',
            'notes' => 'Harap persiapkan portofolio sistem terdistribusi.',
            'status' => 'screening',
        ]);

        $history1 = $application->statusHistories()->create([
            'from_status' => 'wishlist',
            'to_status' => 'applied',
            'note' => 'Lamaran terkirim melalui website rekrutmen.',
            'created_at' => now()->subDays(2),
        ]);

        $history2 = $application->statusHistories()->create([
            'from_status' => 'applied',
            'to_status' => 'screening',
            'note' => 'Lolos verifikasi berkas, masuk tahap screening HR.',
            'created_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->user)->get("/applications/{$application->id}");

        $response->assertOk();
        $response->assertViewIs('applications.show');
        $response->assertSee('Traveloka Indonesia');
        $response->assertSee('Fullstack Developer');
        $response->assertSee('BSD City, Tangerang');
        $response->assertSee('Hybrid');
        $response->assertSee('LinkedIn Jobs');
        $response->assertSee('https://jobs.traveloka.com/positions/fullstack');
        $response->assertSee('Rp 25.000.000 - Rp 30.000.000');
        $response->assertSee('Budi Pratama');
        $response->assertSee('budi.hr@traveloka.com');
        $response->assertSee('Harap persiapkan portofolio sistem terdistribusi.');
        $response->assertSee('Screening');
        $response->assertSee('Informasi Lamaran');
        $response->assertSee('Timeline Riwayat Status');
        $response->assertSee('Ubah Status Cepat');
        $response->assertSee('Tambah Catatan / Progres Interview');
        $response->assertSee('Lamaran terkirim melalui website rekrutmen.');
        $response->assertSee('Lolos verifikasi berkas, masuk tahap screening HR.');
        $response->assertSee(route('applications.edit', $application));
        $response->assertSee(route('applications.destroy', $application));
        $response->assertSee(route('applications.index'));
    }

    public function test_user_can_add_interview_or_progress_note_to_history(): void
    {
        $application = JobApplication::create([
            'company' => 'Shopee Pay',
            'position' => 'Senior QA Engineer',
            'status' => 'interview',
            'applied_at' => '2026-09-30',
        ]);

        $noteText = 'Interview user dengan Engineering Lead selesai. Fokus pertanyaan mengenai CI/CD dan load testing.';

        $response = $this->actingAs($this->user)
            ->from("/applications/{$application->id}")
            ->post("/applications/{$application->id}/histories", [
                'note' => $noteText,
            ]);

        $response->assertRedirect("/applications/{$application->id}");
        $response->assertSessionHas('success', 'Catatan berhasil ditambahkan ke riwayat.');

        $this->assertDatabaseHas('status_histories', [
            'job_application_id' => $application->id,
            'from_status' => 'interview',
            'to_status' => 'interview',
            'note' => $noteText,
        ]);
    }

    public function test_add_history_note_validation_requires_note(): void
    {
        $application = JobApplication::create([
            'company' => 'Blibli',
            'position' => 'DevOps Engineer',
            'status' => 'screening',
            'applied_at' => '2026-09-30',
        ]);

        $response = $this->actingAs($this->user)
            ->from("/applications/{$application->id}")
            ->post("/applications/{$application->id}/histories", [
                'note' => '',
            ]);

        $response->assertRedirect("/applications/{$application->id}");
        $response->assertSessionHasErrors([
            'note' => 'Catatan wajib diisi.',
        ]);

        $this->assertDatabaseMissing('status_histories', [
            'job_application_id' => $application->id,
        ]);
    }

    public function test_add_history_note_validation_enforces_max_length(): void
    {
        $application = JobApplication::create([
            'company' => 'Blibli',
            'position' => 'DevOps Engineer',
            'status' => 'screening',
            'applied_at' => '2026-09-30',
        ]);

        $response = $this->actingAs($this->user)
            ->from("/applications/{$application->id}")
            ->post("/applications/{$application->id}/histories", [
                'note' => str_repeat('a', 5001),
            ]);

        $response->assertSessionHasErrors('note');
    }

    public function test_user_can_change_status_quickly_with_custom_note(): void
    {
        $application = JobApplication::create([
            'company' => 'Astra International',
            'position' => 'System Analyst',
            'status' => 'applied',
            'applied_at' => '2026-09-30',
        ]);

        $response = $this->actingAs($this->user)
            ->from("/applications/{$application->id}")
            ->post("/applications/{$application->id}/quick-status", [
                'status' => 'interview',
                'note' => 'Diundang interview tatap muka di Astra Tower lantai 15.',
            ]);

        $response->assertRedirect("/applications/{$application->id}");
        $response->assertSessionHas('success', 'Status lamaran Astra International berhasil diperbarui.');

        $application->refresh();
        $this->assertEquals('interview', $application->status);
        $this->assertNotNull($application->last_status_change_at);

        $this->assertDatabaseHas('status_histories', [
            'job_application_id' => $application->id,
            'from_status' => 'applied',
            'to_status' => 'interview',
            'note' => 'Diundang interview tatap muka di Astra Tower lantai 15.',
        ]);
    }

    public function test_user_can_change_status_quickly_without_note_uses_default_note(): void
    {
        $application = JobApplication::create([
            'company' => 'Bank Mandiri',
            'position' => 'IT Specialist',
            'status' => 'interview',
            'applied_at' => '2026-09-30',
        ]);

        $response = $this->actingAs($this->user)
            ->from("/applications/{$application->id}")
            ->post("/applications/{$application->id}/quick-status", [
                'status' => 'offer',
                'note' => '',
            ]);

        $response->assertRedirect("/applications/{$application->id}");
        $response->assertSessionHas('success', 'Status lamaran Bank Mandiri berhasil diperbarui.');

        $application->refresh();
        $this->assertEquals('offer', $application->status);

        $this->assertDatabaseHas('status_histories', [
            'job_application_id' => $application->id,
            'from_status' => 'interview',
            'to_status' => 'offer',
            'note' => 'Status diubah ke Offering.',
        ]);
    }

    public function test_quick_status_does_not_create_duplicate_history_if_status_is_the_same(): void
    {
        $application = JobApplication::create([
            'company' => 'Telkom Indonesia',
            'position' => 'Cloud Engineer',
            'status' => 'screening',
            'applied_at' => '2026-09-30',
        ]);

        $initialHistoryCount = $application->statusHistories()->count();

        $response = $this->actingAs($this->user)
            ->from("/applications/{$application->id}")
            ->post("/applications/{$application->id}/quick-status", [
                'status' => 'screening',
                'note' => 'Status tidak berubah',
            ]);

        $response->assertRedirect("/applications/{$application->id}");
        $this->assertEquals($initialHistoryCount, $application->statusHistories()->count());
    }

    public function test_quick_status_validation_requires_status(): void
    {
        $application = JobApplication::create([
            'company' => 'Finaccel (Kredivo)',
            'position' => 'Data Analyst',
            'status' => 'wishlist',
            'applied_at' => '2026-09-30',
        ]);

        $response = $this->actingAs($this->user)
            ->from("/applications/{$application->id}")
            ->post("/applications/{$application->id}/quick-status", [
                'status' => '',
            ]);

        $response->assertSessionHasErrors([
            'status' => 'Status wajib dipilih.',
        ]);
    }

    public function test_quick_status_validation_rejects_invalid_status(): void
    {
        $application = JobApplication::create([
            'company' => 'Finaccel (Kredivo)',
            'position' => 'Data Analyst',
            'status' => 'wishlist',
            'applied_at' => '2026-09-30',
        ]);

        $response = $this->actingAs($this->user)
            ->from("/applications/{$application->id}")
            ->post("/applications/{$application->id}/quick-status", [
                'status' => 'invalid_status_code',
            ]);

        $response->assertSessionHasErrors([
            'status' => 'Status yang dipilih tidak valid.',
        ]);
    }

    public function test_applications_index_links_to_detail_view_and_has_detail_button(): void
    {
        $application = JobApplication::create([
            'company' => 'Tiket Network',
            'position' => 'Site Reliability Engineer',
            'status' => 'applied',
            'applied_at' => '2026-09-30',
        ]);

        $response = $this->actingAs($this->user)->get('/applications');

        $response->assertOk();
        $response->assertSee(route('applications.show', $application));
        $response->assertSee('Detail');
    }
}
