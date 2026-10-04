<?php

namespace Tests\Feature;

use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavedSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_a_saved_search_can_be_stored(): void
    {
        $response = $this->post(route('discovery.saved.store'), [
            'keyword' => 'laravel developer',
            'limit' => 20,
        ]);

        $response->assertRedirect(route('discovery.index'));
        $this->assertDatabaseHas('saved_searches', [
            'user_id' => $this->user->id,
            'keyword' => 'laravel developer',
            'limit' => 20,
            'is_active' => true,
        ]);
    }

    public function test_storing_the_same_keyword_twice_updates_instead_of_duplicating(): void
    {
        $this->post(route('discovery.saved.store'), ['keyword' => 'backend', 'limit' => 10]);
        $this->post(route('discovery.saved.store'), ['keyword' => 'backend', 'limit' => 30]);

        $this->assertSame(1, SavedSearch::where('keyword', 'backend')->count());
        $this->assertSame(30, SavedSearch::first()->limit);
    }

    public function test_a_saved_search_can_be_deleted(): void
    {
        $saved = SavedSearch::create(['keyword' => 'data analyst', 'limit' => 30]);

        $this->delete(route('discovery.saved.destroy', $saved))
            ->assertRedirect(route('discovery.index'));

        $this->assertDatabaseMissing('saved_searches', ['id' => $saved->id]);
    }

    public function test_a_user_cannot_delete_another_users_saved_search(): void
    {
        $other = User::factory()->create();
        $this->actingAs($other);
        $saved = SavedSearch::create(['keyword' => 'rahasia', 'limit' => 30]);

        $this->actingAs($this->user);
        $this->delete(route('discovery.saved.destroy', $saved))->assertNotFound();

        $this->assertDatabaseHas('saved_searches', ['id' => $saved->id]);
    }

    public function test_the_discovery_page_lists_only_own_saved_searches(): void
    {
        SavedSearch::create(['keyword' => 'milik saya', 'limit' => 30]);

        $other = User::factory()->create();
        $this->actingAs($other);
        SavedSearch::create(['keyword' => 'milik orang lain', 'limit' => 30]);

        $response = $this->get(route('discovery.index'));

        $response->assertOk();
        $response->assertSee('milik orang lain');
        $response->assertDontSee('milik saya');
    }

    public function test_keyword_is_required(): void
    {
        $this->post(route('discovery.saved.store'), ['keyword' => ''])
            ->assertSessionHasErrors('keyword');

        $this->assertSame(0, SavedSearch::count());
    }
}
