<?php

namespace Tests\Feature;

use App\Models\InterviewChecklist;
use App\Models\JobApplication;
use App\Models\OfferDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferChecklistTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_unauthenticated_user_cannot_access_checklist_or_offer_routes(): void
    {
        $this->app['auth']->logout();
        $application = JobApplication::create([
            'company' => 'PT Toko Sejahtera',
            'position' => 'Senior Backend Engineer',
            'applied_at' => '2026-09-01',
            'status' => 'interview',
        ]);

        $checklist = InterviewChecklist::create([
            'job_application_id' => $application->id,
            'title' => 'Riset Budaya Perusahaan',
            'is_completed' => false,
        ]);

        // Checklist routes
        $this->post("/applications/{$application->id}/checklists", ['title' => 'Cek Review Glassdoor'])
            ->assertRedirect('/login');
        $this->patch("/checklists/{$checklist->id}/toggle")
            ->assertRedirect('/login');
        $this->delete("/checklists/{$checklist->id}")
            ->assertRedirect('/login');

        // Offer routes
        $this->get('/offers')
            ->assertRedirect('/login');
        $this->post("/applications/{$application->id}/offers", ['base_salary' => 20000000])
            ->assertRedirect('/login');
    }

    public function test_authenticated_user_can_add_interview_checklist_item(): void
    {
        $application = JobApplication::create([
            'company' => 'Gojek',
            'position' => 'Software Engineer',
            'applied_at' => '2026-09-10',
            'status' => 'interview',
        ]);

        $response = $this->actingAs($this->user)
            ->from("/applications/{$application->id}")
            ->post(route('applications.checklists.store', $application), [
                'title' => 'Pelajari Arsitektur Microservices Gojek',
            ]);

        $response->assertRedirect("/applications/{$application->id}");
        $response->assertSessionHas('success', 'Item checklist berhasil ditambahkan.');

        $this->assertDatabaseHas('interview_checklists', [
            'job_application_id' => $application->id,
            'title' => 'Pelajari Arsitektur Microservices Gojek',
            'is_completed' => false,
            'completed_at' => null,
        ]);
    }

    public function test_store_checklist_validation_requires_title(): void
    {
        $application = JobApplication::create([
            'company' => 'Traveloka',
            'position' => 'Site Reliability Engineer',
            'applied_at' => '2026-09-10',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('applications.checklists.store', $application), [
                'title' => '',
            ]);

        $response->assertSessionHasErrors(['title']);
        $this->assertDatabaseCount('interview_checklists', 0);
    }

    public function test_authenticated_user_can_toggle_checklist_item_completion(): void
    {
        $application = JobApplication::create([
            'company' => 'Grab',
            'position' => 'Lead Engineer',
            'applied_at' => '2026-09-12',
            'status' => 'interview',
        ]);

        $checklist = InterviewChecklist::create([
            'job_application_id' => $application->id,
            'title' => 'Persiapan STAR method',
            'is_completed' => false,
            'completed_at' => null,
        ]);

        // Toggle to completed
        $response1 = $this->actingAs($this->user)
            ->from("/applications/{$application->id}")
            ->patch(route('checklists.toggle', $checklist));

        $response1->assertRedirect("/applications/{$application->id}");
        $response1->assertSessionHas('success');

        $checklist->refresh();
        $this->assertTrue($checklist->is_completed);
        $this->assertNotNull($checklist->completed_at);

        // Toggle back to incomplete
        $response2 = $this->actingAs($this->user)
            ->from("/applications/{$application->id}")
            ->patch(route('checklists.toggle', $checklist));

        $response2->assertRedirect("/applications/{$application->id}");
        $checklist->refresh();
        $this->assertFalse($checklist->is_completed);
        $this->assertNull($checklist->completed_at);
    }

    public function test_authenticated_user_can_delete_checklist_item(): void
    {
        $application = JobApplication::create([
            'company' => 'Bukalapak',
            'position' => 'Frontend Engineer',
            'applied_at' => '2026-09-15',
        ]);

        $checklist = InterviewChecklist::create([
            'job_application_id' => $application->id,
            'title' => 'Review React Server Components',
            'is_completed' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->from("/applications/{$application->id}")
            ->delete(route('checklists.destroy', $checklist));

        $response->assertRedirect("/applications/{$application->id}");
        $response->assertSessionHas('success', 'Item checklist berhasil dihapus.');

        $this->assertDatabaseMissing('interview_checklists', [
            'id' => $checklist->id,
        ]);
    }

    public function test_authenticated_user_can_save_offer_details(): void
    {
        $application = JobApplication::create([
            'company' => 'Sea Group',
            'position' => 'Full Stack Developer',
            'applied_at' => '2026-09-01',
            'status' => 'offer',
        ]);

        $response = $this->actingAs($this->user)
            ->from("/applications/{$application->id}")
            ->post(route('offers.save', $application), [
                'base_salary' => 28000000,
                'salary_period' => 'monthly',
                'thr' => '1x Gaji Pokok',
                'bonus' => 'Performance Bonus s.d. 3x gaji',
                'allowance' => 'Makan & Transport Rp 2.500.000',
                'health_insurance' => 'BPJS + Prudential Rawat Inap & Jalan',
                'work_scheme' => 'Hybrid',
                'deadline_at' => '2026-10-20',
                'notes' => 'Ada bonus laptop MacBook Pro M3',
            ]);

        $response->assertRedirect("/applications/{$application->id}");
        $response->assertSessionHas('success', 'Detail penawaran kerja berhasil disimpan.');

        $this->assertDatabaseHas('offer_details', [
            'job_application_id' => $application->id,
            'base_salary' => 28000000,
            'salary_period' => 'monthly',
            'thr' => '1x Gaji Pokok',
            'bonus' => 'Performance Bonus s.d. 3x gaji',
            'allowance' => 'Makan & Transport Rp 2.500.000',
            'health_insurance' => 'BPJS + Prudential Rawat Inap & Jalan',
            'work_scheme' => 'Hybrid',
            'notes' => 'Ada bonus laptop MacBook Pro M3',
        ]);
        $this->assertEquals('2026-10-20', $application->fresh()->offerDetail->deadline_at->format('Y-m-d'));
    }

    public function test_authenticated_user_can_update_existing_offer_details(): void
    {
        $application = JobApplication::create([
            'company' => 'Blibli',
            'position' => 'Backend Engineer',
            'applied_at' => '2026-09-05',
            'status' => 'offer',
        ]);

        OfferDetail::create([
            'job_application_id' => $application->id,
            'base_salary' => 20000000,
            'salary_period' => 'monthly',
            'work_scheme' => 'Onsite',
        ]);

        $this->actingAs($this->user)
            ->post(route('offers.save', $application), [
                'base_salary' => 24000000,
                'salary_period' => 'monthly',
                'work_scheme' => 'Remote',
                'notes' => 'Berhasil negosiasi naik 4 juta dan full remote',
            ]);

        $this->assertDatabaseCount('offer_details', 1);
        $this->assertDatabaseHas('offer_details', [
            'job_application_id' => $application->id,
            'base_salary' => 24000000,
            'work_scheme' => 'Remote',
            'notes' => 'Berhasil negosiasi naik 4 juta dan full remote',
        ]);
    }

    public function test_offer_details_validation_checks_numeric_base_salary_and_valid_date(): void
    {
        $application = JobApplication::create([
            'company' => 'Fintech ID',
            'position' => 'DevOps Specialist',
            'applied_at' => '2026-09-08',
            'status' => 'offer',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('offers.save', $application), [
                'base_salary' => 'bukan_angka',
                'deadline_at' => 'bukan_tanggal',
            ]);

        $response->assertSessionHasErrors(['base_salary', 'deadline_at']);
    }

    public function test_authenticated_user_can_view_offer_comparison_page(): void
    {
        // App 1: status offer with offer detail
        $app1 = JobApplication::create([
            'company' => 'Unicorn Tech',
            'position' => 'Senior Backend Engineer',
            'applied_at' => '2026-09-01',
            'status' => 'offer',
        ]);
        OfferDetail::create([
            'job_application_id' => $app1->id,
            'base_salary' => 35000000,
            'salary_period' => 'monthly',
            'thr' => '1x Gaji Pokok',
            'bonus' => 'Annual Bonus 2x',
            'allowance' => 'Rp 2.000.000',
            'health_insurance' => 'BPJS + Prudential',
            'work_scheme' => 'Remote',
            'deadline_at' => '2026-10-15',
            'notes' => 'Stock options included',
        ]);

        // App 2: status hired with offer detail
        $app2 = JobApplication::create([
            'company' => 'Global Decacorn',
            'position' => 'Staff Software Engineer',
            'applied_at' => '2026-09-02',
            'status' => 'hired',
        ]);
        OfferDetail::create([
            'job_application_id' => $app2->id,
            'base_salary' => 45000000,
            'salary_period' => 'monthly',
            'work_scheme' => 'Hybrid',
            'notes' => 'Signed agreement',
        ]);

        // App 3: status interview, has offerDetail
        $app3 = JobApplication::create([
            'company' => 'Startup X',
            'position' => 'Tech Lead',
            'applied_at' => '2026-09-03',
            'status' => 'interview',
        ]);
        OfferDetail::create([
            'job_application_id' => $app3->id,
            'base_salary' => 30000000,
            'salary_period' => 'monthly',
            'work_scheme' => 'Remote',
        ]);

        // App 4: status applied, NO offerDetail (should NOT appear)
        $app4 = JobApplication::create([
            'company' => 'Ignore Co',
            'position' => 'Junior Developer',
            'applied_at' => '2026-09-04',
            'status' => 'applied',
        ]);

        $response = $this->actingAs($this->user)->get(route('offers.index'));

        $response->assertStatus(200);
        $response->assertSee('Matriks Komparasi Offer');
        $response->assertSee('Unicorn Tech');
        $response->assertSee('Senior Backend Engineer');
        $response->assertSee('35.000.000');
        $response->assertSee('Global Decacorn');
        $response->assertSee('45.000.000');
        $response->assertSee('Startup X');
        $response->assertDontSee('Ignore Co');
    }

    public function test_offer_comparison_shows_empty_state_when_no_offers(): void
    {
        // Only non-offer applications without offer detail
        JobApplication::create([
            'company' => 'Company A',
            'position' => 'Developer',
            'applied_at' => '2026-09-01',
            'status' => 'applied',
        ]);

        $response = $this->actingAs($this->user)->get(route('offers.index'));

        $response->assertStatus(200);
        $response->assertSee('Belum Ada Penawaran Kerja Tercatat');
        $response->assertSee('Lihat Daftar Lamaran');
    }

    public function test_application_show_page_displays_checklist_and_preset_buttons(): void
    {
        $application = JobApplication::create([
            'company' => 'Astra Digital',
            'position' => 'Backend Architect',
            'applied_at' => '2026-09-01',
            'status' => 'interview',
        ]);

        InterviewChecklist::create([
            'job_application_id' => $application->id,
            'title' => 'Pelajari Arsitektur Event-Driven',
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->get(route('applications.show', $application));

        $response->assertStatus(200);
        $response->assertSee('Checklist Persiapan Interview');
        $response->assertSee('Pelajari Arsitektur Event-Driven');
        $response->assertSee('+ Riset Perusahaan');
        $response->assertSee('+ Persiapan Behavioral (STAR)');
        $response->assertSee('+ Review System Design / Coding');
    }

    public function test_application_show_page_displays_offer_card_when_status_is_offer(): void
    {
        $application = JobApplication::create([
            'company' => 'Dana Indonesia',
            'position' => 'Principal Engineer',
            'applied_at' => '2026-09-01',
            'status' => 'offer',
        ]);

        $response = $this->actingAs($this->user)->get(route('applications.show', $application));

        $response->assertStatus(200);
        $response->assertSee('Detail Penawaran Kerja (Offer Details)');
        $response->assertSee('Masukkan Rincian Penawaran Kerja');
    }
}
