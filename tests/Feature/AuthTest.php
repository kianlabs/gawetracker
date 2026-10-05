<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_page(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_login_page_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('GaweTracker');
        $response->assertSee('Kata Sandi');
    }

    public function test_registration_page_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertSee('GaweTracker');
        $response->assertSee('Konfirmasi Kata Sandi');
    }

    public function test_new_user_can_register_and_is_logged_in(): void
    {
        $response = $this->post('/register', [
            'name' => 'Pengguna Baru',
            'email' => 'baru@gawetracker.test',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'baru@gawetracker.test']);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'sudah@ada.test']);

        $response = $this->from('/register')->post('/register', [
            'name' => 'Pengguna Baru',
            'email' => 'sudah@ada.test',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ]);

        $this->assertGuest();
        $response->assertRedirect('/register');
        $response->assertSessionHasErrors(['email' => 'Email ini sudah terdaftar.']);
    }

    public function test_registration_rejects_mismatched_password_confirmation(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'Pengguna Baru',
            'email' => 'baru@gawetracker.test',
            'password' => 'rahasia123',
            'password_confirmation' => 'beda456',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors(['password' => 'Konfirmasi kata sandi tidak cocok.']);
    }

    public function test_registration_is_unavailable_when_flag_is_disabled(): void
    {
        config(['auth.registration_enabled' => false]);

        $this->get('/register')->assertStatus(404);

        $this->post('/register', [
            'name' => 'Pengguna Baru',
            'email' => 'baru@gawetracker.test',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertStatus(404);

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'baru@gawetracker.test']);
    }

    public function test_single_user_can_authenticate_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'kyan@gawetracker.test',
            'password' => Hash::make('password'),
        ]);

        $response = $this->post('/login', [
            'email' => 'kyan@gawetracker.test',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/');
    }

    public function test_authentication_fails_with_invalid_credentials_and_indonesian_message(): void
    {
        User::factory()->create([
            'email' => 'kyan@gawetracker.test',
            'password' => Hash::make('password'),
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'kyan@gawetracker.test',
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertRedirect('/login');
        $response->assertSessionHasErrors([
            'email' => 'Email atau kata sandi yang Anda masukkan salah.',
        ]);
    }

    public function test_validation_errors_are_in_indonesian_when_fields_are_empty(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => '',
            'password' => '',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors([
            'email' => 'Email wajib diisi.',
            'password' => 'Password wajib diisi.',
        ]);
    }

    public function test_authenticated_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/login');
    }

    public function test_spoofed_forwarded_for_cannot_bypass_the_login_throttle(): void
    {
        // Behind the Cloudflare Tunnel every request reaches the app from the
        // docker gateway, so that private address is the only trusted proxy.
        // cloudflared appends the real client IP on the right of
        // X-Forwarded-For; a client may prepend arbitrary entries on the left.
        // Those forged entries must never become the rate-limit key, or an
        // attacker could brute force passwords by rotating them.
        for ($i = 1; $i <= 8; $i++) {
            $response = $this
                ->withServerVariables(['REMOTE_ADDR' => '172.20.0.1'])
                ->withHeaders(['X-Forwarded-For' => "10.9.9.{$i},182.9.1.39"])
                ->from('/login')
                ->post('/login', [
                    'email' => 'kyan@gawetracker.test',
                    'password' => 'wrong-password',
                ]);

            if ($i <= 5) {
                $response->assertRedirect('/login');
            } else {
                $response->assertStatus(429);
            }
        }
    }
}
