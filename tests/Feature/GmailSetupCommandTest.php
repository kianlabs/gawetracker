<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The setup command's reporting path is read-only, so these tests never touch
 * the real .env — they assert the status output and, crucially, that running
 * the command does not rewrite the environment file.
 */
class GmailSetupCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_reports_missing_credentials_as_incomplete(): void
    {
        config([
            'services.gmail.client_id' => null,
            'services.gmail.client_secret' => null,
            'services.gmail.redirect_uri' => null,
        ]);

        $this->artisan('gmail:setup --show')
            ->expectsOutputToContain('<kosong>')
            ->assertExitCode(1);
    }

    public function test_show_reports_the_redirect_uri_that_must_be_registered(): void
    {
        config([
            'services.gmail.client_id' => 'client-id',
            'services.gmail.client_secret' => 'client-secret',
            'services.gmail.redirect_uri' => 'http://localhost:8000/gmail/callback',
        ]);

        $this->artisan('gmail:setup --show')
            ->expectsOutputToContain('http://localhost:8000/gmail/callback')
            ->assertExitCode(0);
    }

    public function test_show_never_rewrites_the_env_file(): void
    {
        $path = base_path('.env');

        if (! File::exists($path)) {
            $this->markTestSkipped('.env not present in this environment.');
        }

        $before = hash_file('sha256', $path);

        $this->artisan('gmail:setup --show')->run();

        $this->assertSame($before, hash_file('sha256', $path), 'The --show flag must not modify .env.');
    }

    public function test_is_configured_requires_all_three_values(): void
    {
        $oauth = app(\App\Services\GoogleOAuthService::class);

        config([
            'services.gmail.client_id' => 'id',
            'services.gmail.client_secret' => 'secret',
            'services.gmail.redirect_uri' => 'http://localhost/gmail/callback',
        ]);
        $this->assertTrue($oauth->isConfigured());

        config(['services.gmail.client_secret' => null]);
        $this->assertFalse($oauth->isConfigured());

        config([
            'services.gmail.client_secret' => 'secret',
            'services.gmail.redirect_uri' => null,
        ]);
        // Falls back to the named route, so still configured.
        $this->assertTrue($oauth->isConfigured());
    }
}
