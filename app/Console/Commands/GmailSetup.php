<?php

namespace App\Console\Commands;

use App\Services\GoogleOAuthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Guided setup for the "Connect Gmail" OAuth flow.
 *
 * Google's OAuth client credentials must live in .env. Hand-editing that file is
 * error-prone (a trailing space in the secret produces an opaque
 * "invalid_client" from Google), so this command writes them safely and shows
 * exactly which redirect URI must be registered in the Google Cloud Console.
 *
 * The secret is read with a hidden prompt, so it never lands in shell history.
 */
class GmailSetup extends Command
{
    protected $signature = 'gmail:setup
        {--show : Only print the current status and checklist}
        {--clear : Remove the stored OAuth credentials from .env}';

    protected $description = 'Configure and verify the Google OAuth client used to connect Gmail';

    public function handle(GoogleOAuthService $oauth): int
    {
        if ($this->option('clear')) {
            $this->writeEnv('GOOGLE_CLIENT_ID', '');
            $this->writeEnv('GOOGLE_CLIENT_SECRET', '');
            $this->call('config:clear');
            $this->info('Kredensial OAuth dihapus dari .env.');

            return self::SUCCESS;
        }

        if (! $this->option('show') && ! $oauth->isConfigured()) {
            $this->promptForCredentials();
        }

        return $this->reportStatus($oauth);
    }

    /**
     * Ask for the two credentials and persist them. An empty answer keeps the
     * existing value, so re-running the command never wipes a working setup.
     */
    private function promptForCredentials(): void
    {
        $this->newLine();
        $this->line('Masukkan kredensial OAuth dari Google Cloud Console.');
        $this->line('Kosongkan (langsung Enter) untuk mempertahankan nilai yang sudah ada.');
        $this->newLine();

        $id = trim((string) $this->ask('GOOGLE_CLIENT_ID'));
        if ($id !== '') {
            $this->writeEnv('GOOGLE_CLIENT_ID', $id);
        }

        // Hidden input: never echoed, never in shell history.
        $secret = trim((string) $this->secret('GOOGLE_CLIENT_SECRET'));
        if ($secret !== '') {
            $this->writeEnv('GOOGLE_CLIENT_SECRET', $secret);
        }

        $this->writeEnv('GOOGLE_REDIRECT_URI', '"${APP_URL}/gmail/callback"');

        // Clear the on-disk config cache, then refresh this process's config
        // from .env. Without the refresh the status report below would print the
        // pre-write values and falsely claim the credentials are still empty.
        $this->call('config:clear');
        $this->syncConfigFromEnv();
    }

    /**
     * Re-read the three OAuth keys from .env into the running config, resolving
     * the ${APP_URL} placeholder the same way Dotenv does.
     */
    private function syncConfigFromEnv(): void
    {
        config([
            'services.gmail.client_id' => $this->readEnv('GOOGLE_CLIENT_ID'),
            'services.gmail.client_secret' => $this->readEnv('GOOGLE_CLIENT_SECRET'),
            'services.gmail.redirect_uri' => $this->readEnv('GOOGLE_REDIRECT_URI'),
        ]);
    }

    /**
     * Read a single value from .env, stripping surrounding quotes and expanding
     * the ${APP_URL} placeholder. Returns null for a missing or empty value.
     */
    private function readEnv(string $key): ?string
    {
        $contents = File::get(base_path('.env'));

        if (! preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $contents, $matches)) {
            return null;
        }

        $value = trim($matches[1]);
        $value = trim($value, '"');
        $value = str_replace('${APP_URL}', (string) config('app.url'), $value);

        return $value === '' ? null : $value;
    }

    private function reportStatus(GoogleOAuthService $oauth): int
    {
        $configured = $oauth->isConfigured();
        $redirect = $oauth->redirectUri();
        $hasToken = \App\Models\User::whereNotNull('gmail_refresh_token')->exists();

        $this->newLine();
        $this->line('  Status Koneksi Gmail');
        $this->line('  ────────────────────────────────────────────');
        $this->line('  Client ID      : '.(filled(config('services.gmail.client_id')) ? '<terisi>' : '<kosong>'));
        $this->line('  Client Secret  : '.(filled(config('services.gmail.client_secret')) ? '<terisi>' : '<kosong>'));
        $this->line('  Redirect URI   : '.$redirect);
        $this->line('  Akun tersambung: '.($hasToken ? 'ya' : 'belum'));
        $this->line('  Konfigurasi    : '.($configured ? '<lengkap>' : '<belum lengkap>'));
        $this->newLine();

        if (! $configured) {
            $this->warn('Kredensial belum lengkap. Jalankan `php artisan gmail:setup` untuk mengisinya.');

            return self::FAILURE;
        }

        $this->info('Kredensial sudah lengkap. Langkah berikutnya:');
        $this->newLine();
        $this->line('  1. Google Cloud Console → APIs & Services → Credentials');
        $this->line('     Pastikan OAuth client bertipe "Web application".');
        $this->line('  2. Di "Authorized redirect URIs", tambahkan PERSIS:');
        $this->line('       '.$redirect);
        $this->line('  3. APIs & Services → Library → aktifkan "Gmail API".');
        $this->line('  4. Jalankan aplikasi di '.config('app.url').' (harus sama dengan APP_URL):');
        $this->line('       php artisan serve');
        $this->line('  5. Login, lalu klik "Hubungkan Gmail" di halaman Dashboard.');
        $this->line('  6. Setujui consent. Setelah itu impor email:');
        $this->line('       php artisan emails:import --gmail --dry-run');
        $this->newLine();

        if ($hasToken) {
            $this->info('Akun sudah tersambung — cukup jalankan `php artisan emails:import --gmail`.');
        }

        return self::SUCCESS;
    }

    /**
     * Set KEY=value in .env, replacing the line when it exists and appending
     * otherwise. Values are written verbatim (the caller quotes when needed).
     */
    private function writeEnv(string $key, string $value): void
    {
        $path = base_path('.env');

        if (! File::exists($path)) {
            $this->error('.env tidak ditemukan.');

            return;
        }

        $contents = File::get($path);
        $line = $key.'='.$value;

        if (preg_match('/^'.preg_quote($key, '/').'=.*$/m', $contents)) {
            $contents = preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $line, $contents, 1);
        } else {
            $contents = rtrim($contents, "\n")."\n".$line."\n";
        }

        File::put($path, $contents);
    }
}
