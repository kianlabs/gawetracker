<?php

namespace Tests\Feature;

use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickAddExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_unauthenticated_user_blocked_from_quick_store_and_export(): void
    {
        $this->app['auth']->logout();
        $this->post('/applications/quick', [
            'company' => 'Bukalapak',
            'position' => 'Backend Engineer',
        ])->assertRedirect('/login');

        $this->get('/applications/export')->assertRedirect('/login');
    }

    public function test_quick_store_creates_application_and_initial_status_history(): void
    {
        $response = $this->actingAs($this->user)
            ->from('/dashboard')
            ->post('/applications/quick', [
                'company' => 'PT Tokopedia',
                'position' => 'Senior Backend Engineer',
            ]);

        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('success', 'Lamaran berhasil ditambahkan dalam waktu kilat!');

        $this->assertDatabaseHas('job_applications', [
            'company' => 'PT Tokopedia',
            'position' => 'Senior Backend Engineer',
            'status' => 'wishlist',
        ]);

        $application = JobApplication::where('company', 'PT Tokopedia')->first();
        $this->assertNotNull($application);
        $this->assertNotNull($application->last_status_change_at);

        $this->assertCount(1, $application->statusHistories);
        $history = $application->statusHistories->first();
        $this->assertNull($history->from_status);
        $this->assertEquals('wishlist', $history->to_status);
        $this->assertEquals('Lamaran dibuat via Quick Add.', $history->note);
    }

    public function test_quick_store_supports_custom_status_and_details(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/applications/quick', [
                'company' => 'Shopee Indonesia',
                'position' => 'Engineering Lead',
                'source_url' => 'https://careers.shopee.co.id/job/12345',
                'work_type' => 'hybrid',
                'applied_at' => '2026-09-15',
                'status' => 'applied',
            ]);

        $response->assertSessionHas('success', 'Lamaran berhasil ditambahkan dalam waktu kilat!');

        $this->assertDatabaseHas('job_applications', [
            'company' => 'Shopee Indonesia',
            'position' => 'Engineering Lead',
            'source_url' => 'https://careers.shopee.co.id/job/12345',
            'work_type' => 'hybrid',
            'applied_at' => '2026-09-15 00:00:00',
            'status' => 'applied',
        ]);

        $application = JobApplication::where('company', 'Shopee Indonesia')->first();
        $this->assertNotNull($application);
        $history = $application->statusHistories->first();
        $this->assertEquals('applied', $history->to_status);
        $this->assertEquals('Lamaran dibuat via Quick Add.', $history->note);
    }

    public function test_quick_store_validation_requires_company_and_position(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/applications/quick', [
                'company' => '',
                'position' => '',
                'source_url' => 'not-a-valid-url',
                'work_type' => 'invalid_type',
                'status' => 'invalid_status',
            ]);

        $response->assertSessionHasErrors(['company', 'position', 'source_url', 'work_type', 'status']);
    }

    public function test_export_csv_returns_status_200_with_csv_headers_and_bom(): void
    {
        JobApplication::create([
            'company' => 'PT Karya Cipta',
            'position' => 'Software Engineer',
            'location' => 'Jakarta',
            'work_type' => 'remote',
            'source' => 'LinkedIn',
            'source_url' => 'https://example.com/job/1',
            'applied_at' => '2026-09-20',
            'status' => 'applied',
            'salary_note' => 'Rp 20.000.000',
            'contact_name' => 'Budi Santoso',
            'contact_info' => 'budi@example.com',
            'notes' => 'Catatan tes dengan karakter khusus: é, à, ü.',
            'last_status_change_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->get(route('applications.export'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('.csv', (string) $response->headers->get('content-disposition'));

        $content = $response->streamedContent();

        // Check UTF-8 BOM
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        // Check header columns
        $expectedHeaders = [
            'ID',
            'Perusahaan',
            'Posisi',
            'Lokasi',
            'Tipe Kerja',
            'Sumber',
            'Tautan Lowongan',
            'Tanggal Melamar',
            'Status',
            'Catatan Gaji',
            'Nama Kontak',
            'Info Kontak',
            'Catatan Bebas',
            'Dibuat Pada',
        ];

        foreach ($expectedHeaders as $header) {
            $this->assertStringContainsString($header, $content);
        }

        // Check data row
        $this->assertStringContainsString('PT Karya Cipta', $content);
        $this->assertStringContainsString('Software Engineer', $content);
        $this->assertStringContainsString('Jakarta', $content);
        $this->assertStringContainsString('Remote', $content);
        $this->assertStringContainsString('Terkirim', $content);
        $this->assertStringContainsString('Catatan tes dengan karakter khusus', $content);
    }

    public function test_export_csv_respects_filters(): void
    {
        JobApplication::create([
            'company' => 'Perusahaan Alpha',
            'position' => 'DevOps Engineer',
            'status' => 'interview',
            'applied_at' => '2026-09-10',
            'last_status_change_at' => now(),
        ]);

        JobApplication::create([
            'company' => 'Perusahaan Beta',
            'position' => 'Product Manager',
            'status' => 'wishlist',
            'applied_at' => '2026-09-11',
            'last_status_change_at' => now(),
        ]);

        // Filter by status=interview
        $response = $this->actingAs($this->user)->get(route('applications.export', ['status' => 'interview']));
        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('Perusahaan Alpha', $content);
        $this->assertStringNotContainsString('Perusahaan Beta', $content);

        // Filter by search=Beta
        $response = $this->actingAs($this->user)->get(route('applications.export', ['search' => 'Beta']));
        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('Perusahaan Beta', $content);
        $this->assertStringNotContainsString('Perusahaan Alpha', $content);
    }

    public function test_dashboard_renders_quick_add_button_and_modal(): void
    {
        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Quick Add');
        $response->assertSee('quickAddModal', false);
        $response->assertSee('Simpan Kilat');
        $response->assertSee('Batal');
    }

    public function test_applications_index_renders_export_csv_link(): void
    {
        $response = $this->actingAs($this->user)->get(route('applications.index'));

        $response->assertOk();
        $response->assertSee('Ekspor CSV');
        $response->assertSee(route('applications.export'));
    }
}
