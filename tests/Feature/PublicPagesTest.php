<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public informational pages exist so Google can verify the OAuth brand:
 * a home page, a privacy policy and terms of service, all reachable without a
 * session on the same domain as the OAuth client.
 */
class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_about_page_is_reachable_without_a_session(): void
    {
        $response = $this->get('/about');

        $response->assertOk();
        $response->assertViewIs('public.about');
        $response->assertSee('GaweTracker');
    }

    public function test_privacy_page_is_reachable_without_a_session(): void
    {
        $response = $this->get('/privacy');

        $response->assertOk();
        $response->assertViewIs('public.privacy');
        $response->assertSee('Kebijakan Privasi');
    }

    public function test_privacy_page_discloses_gmail_data_handling(): void
    {
        $response = $this->get('/privacy');

        $response->assertOk();
        // Google's Limited Use requirements: disclose access, use and deletion.
        $response->assertSee('gmail.readonly');
        $response->assertSee('Limited Use');
        $response->assertSee('Google API Services User Data Policy');
    }

    public function test_terms_page_is_reachable_without_a_session(): void
    {
        $response = $this->get('/terms');

        $response->assertOk();
        $response->assertViewIs('public.terms');
        $response->assertSee('Ketentuan Layanan');
    }

    public function test_public_pages_link_to_each_other(): void
    {
        $this->get('/about')->assertSee(route('privacy'))->assertSee(route('terms'));
        $this->get('/privacy')->assertSee(route('terms'));
        $this->get('/terms')->assertSee(route('privacy'));
    }

    public function test_public_pages_are_viewable_while_authenticated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/about')->assertOk();
        $this->actingAs($user)->get('/privacy')->assertOk();
        $this->actingAs($user)->get('/terms')->assertOk();
    }

    public function test_public_pages_do_not_leak_the_authenticated_user_name(): void
    {
        $user = User::factory()->create(['name' => 'Nama Rahasia Pengguna']);

        $this->actingAs($user)->get('/about')
            ->assertOk()
            ->assertDontSee('Nama Rahasia Pengguna');
    }
}
