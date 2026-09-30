<?php

namespace Tests\Feature;

use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KanbanTest extends TestCase
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
        $response = $this->get('/applications/kanban');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_kanban_board(): void
    {
        $response = $this->actingAs($this->user)->get('/applications/kanban');

        $response->assertStatus(200);
        $response->assertSee('Kanban Board Lamaran');
        $response->assertSee('Tampilan Tabel');
        $response->assertSee('+ Tambah Lamaran');

        // Verify all 7 stage columns are displayed
        $response->assertSee('Wishlist');
        $response->assertSee('Terkirim (Applied)');
        $response->assertSee('Screening');
        $response->assertSee('Interview');
        $response->assertSee('Offering');
        $response->assertSee('Diterima (Hired)');
        $response->assertSee('Ditolak (Rejected)');
    }

    public function test_applications_are_correctly_grouped_into_status_columns(): void
    {
        $wishlist = JobApplication::create([
            'company' => 'Startup A',
            'position' => 'Fullstack Developer',
            'status' => 'wishlist',
            'applied_at' => now()->subDays(10),
        ]);

        $applied = JobApplication::create([
            'company' => 'Perusahaan B',
            'position' => 'Backend Engineer',
            'status' => 'applied',
            'applied_at' => now()->subDays(8),
        ]);

        $screening = JobApplication::create([
            'company' => 'Tech C',
            'position' => 'DevOps Engineer',
            'status' => 'screening',
            'applied_at' => now()->subDays(6),
        ]);

        $interview = JobApplication::create([
            'company' => 'Corp D',
            'position' => 'Mobile Developer',
            'status' => 'interview',
            'applied_at' => now()->subDays(4),
        ]);

        $offer = JobApplication::create([
            'company' => 'Unicorn E',
            'position' => 'Engineering Lead',
            'status' => 'offer',
            'applied_at' => now()->subDays(2),
        ]);

        $hired = JobApplication::create([
            'company' => 'Enterprise F',
            'position' => 'Senior Developer',
            'status' => 'hired',
            'applied_at' => now()->subDays(1),
        ]);

        $rejected = JobApplication::create([
            'company' => 'Agency G',
            'position' => 'Junior Developer',
            'status' => 'rejected',
            'applied_at' => now()->subDays(15),
        ]);

        $response = $this->actingAs($this->user)->get('/applications/kanban');

        $response->assertStatus(200);

        // Check view data grouping
        $response->assertViewHas('applications');
        $response->assertViewHas('counts');
        $response->assertViewHas('columns');

        $applications = $response->viewData('applications');
        $counts = $response->viewData('counts');

        $this->assertCount(1, $applications['wishlist']);
        $this->assertTrue($applications['wishlist']->first()->is($wishlist));
        $this->assertEquals(1, $counts['wishlist']);

        $this->assertCount(1, $applications['applied']);
        $this->assertTrue($applications['applied']->first()->is($applied));
        $this->assertEquals(1, $counts['applied']);

        $this->assertCount(1, $applications['screening']);
        $this->assertTrue($applications['screening']->first()->is($screening));
        $this->assertEquals(1, $counts['screening']);

        $this->assertCount(1, $applications['interview']);
        $this->assertTrue($applications['interview']->first()->is($interview));
        $this->assertEquals(1, $counts['interview']);

        $this->assertCount(1, $applications['offer']);
        $this->assertTrue($applications['offer']->first()->is($offer));
        $this->assertEquals(1, $counts['offer']);

        $this->assertCount(1, $applications['hired']);
        $this->assertTrue($applications['hired']->first()->is($hired));
        $this->assertEquals(1, $counts['hired']);

        $this->assertCount(1, $applications['rejected']);
        $this->assertTrue($applications['rejected']->first()->is($rejected));
        $this->assertEquals(1, $counts['rejected']);

        // Check rendered content on HTML
        $response->assertSee('Startup A');
        $response->assertSee('Perusahaan B');
        $response->assertSee('Tech C');
        $response->assertSee('Corp D');
        $response->assertSee('Unicorn E');
        $response->assertSee('Enterprise F');
        $response->assertSee('Agency G');
    }

    public function test_kanban_card_displays_details_and_quick_status_form(): void
    {
        $app = JobApplication::create([
            'company' => 'Bukalapak',
            'position' => 'Senior Backend Engineer',
            'location' => 'Jakarta Selatan',
            'work_type' => 'remote',
            'salary_note' => 'Rp 25.000.000',
            'status' => 'interview',
            'applied_at' => now()->subDays(5),
            'last_status_change_at' => now()->subDays(3),
        ]);

        $response = $this->actingAs($this->user)->get('/applications/kanban');

        $response->assertStatus(200);
        $response->assertSee('Bukalapak');
        $response->assertSee('Senior Backend Engineer');
        $response->assertSee('Jakarta Selatan');
        $response->assertSee('Remote');
        $response->assertSee('Rp 25.000.000');
        $response->assertSee('Menunggu 3 hari');
        $response->assertSee(route('applications.show', $app));
        $response->assertSee(route('applications.quick-status', $app));
    }

    public function test_kanban_displays_empty_column_placeholder_when_no_applications(): void
    {
        $response = $this->actingAs($this->user)->get('/applications/kanban');

        $response->assertStatus(200);
        $response->assertSee('Tidak ada lamaran');
    }

    public function test_kanban_can_filter_by_search_query(): void
    {
        JobApplication::create([
            'company' => 'Tokopedia',
            'position' => 'Backend Engineer',
            'status' => 'applied',
            'applied_at' => now(),
        ]);

        JobApplication::create([
            'company' => 'Traveloka',
            'position' => 'Frontend Engineer',
            'status' => 'interview',
            'applied_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->get('/applications/kanban?search=Tokopedia');

        $response->assertStatus(200);
        $response->assertSee('Tokopedia');
        $response->assertDontSee('Traveloka');

        $responsePosition = $this->actingAs($this->user)->get('/applications/kanban?search=Frontend');
        $responsePosition->assertStatus(200);
        $responsePosition->assertSee('Traveloka');
        $responsePosition->assertDontSee('Tokopedia');
    }

    public function test_kanban_can_filter_by_work_type(): void
    {
        JobApplication::create([
            'company' => 'Gojek Remote',
            'position' => 'Software Engineer',
            'work_type' => 'remote',
            'status' => 'interview',
            'applied_at' => now(),
        ]);

        JobApplication::create([
            'company' => 'Bank Mandiri Onsite',
            'position' => 'IT Specialist',
            'work_type' => 'onsite',
            'status' => 'applied',
            'applied_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->get('/applications/kanban?work_type=remote');

        $response->assertStatus(200);
        $response->assertSee('Gojek Remote');
        $response->assertDontSee('Bank Mandiri Onsite');
    }

    public function test_quick_status_updates_application_stage(): void
    {
        $app = JobApplication::create([
            'company' => 'Shopee',
            'position' => 'Platform Engineer',
            'status' => 'applied',
            'applied_at' => now()->subDays(3),
        ]);

        $response = $this->actingAs($this->user)
            ->from('/applications/kanban')
            ->post("/applications/{$app->id}/quick-status", [
                'status' => 'interview',
            ]);

        $response->assertRedirect('/applications/kanban');
        $response->assertSessionHas('success');

        $app->refresh();
        $this->assertEquals('interview', $app->status);
        $this->assertNotNull($app->last_status_change_at);
        $this->assertDatabaseHas('status_histories', [
            'job_application_id' => $app->id,
            'from_status' => 'applied',
            'to_status' => 'interview',
        ]);
    }
}
