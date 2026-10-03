<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\EmailIngestionService;
use App\Services\GoogleOAuthService;
use App\Support\Email\GmailClient;
use App\Support\Email\JobEmailParser;
use Illuminate\Console\Command;

class ImportJobEmails extends Command
{
    protected $signature = 'emails:import
        {path? : File or directory of .eml messages to ingest}
        {--gmail : Pull from Gmail instead of a local path (needs GMAIL_ACCESS_TOKEN)}
        {--max=100 : Maximum number of Gmail messages to scan}
        {--dry-run : Parse and report without writing to the database}';

    protected $description = 'Ingest JobStreet/Glints application emails into GaweTracker';

    public function handle(EmailIngestionService $ingestion, JobEmailParser $parser): int
    {
        return $this->option('gmail')
            ? $this->fromGmail($ingestion, $parser)
            : $this->fromPath($ingestion, $parser);
    }

    private function fromPath(EmailIngestionService $ingestion, JobEmailParser $parser): int
    {
        $path = $this->argument('path');
        if (! $path || (! is_file($path) && ! is_dir($path))) {
            $this->error('Provide a readable .eml file or directory (or use --gmail).');

            return self::FAILURE;
        }

        $files = is_dir($path)
            ? glob(rtrim($path, '/').'/*.{eml,txt}', GLOB_BRACE) ?: []
            : [$path];

        $ingested = 0;
        $skipped = 0;

        foreach ($files as $file) {
            $parsed = $parser->parseRaw((string) file_get_contents($file));

            if ($this->option('dry-run')) {
                $this->line(sprintf(
                    '  [dry-run] %s → provider=%s status=%s company=%s',
                    basename($file),
                    $parsed->provider,
                    $parsed->status ?? '-',
                    $parsed->company ?? '-',
                ));

                continue;
            }

            $ledger = $ingestion->ingest($parsed);
            if ($ledger === null) {
                $skipped++;
            } else {
                $ingested++;
                $this->info(sprintf(
                    '  ✓ %s → %s (%s)',
                    basename($file),
                    $ledger->classified_status,
                    $ledger->provider,
                ));
            }
        }

        $this->newLine();
        $this->line("Ingested: {$ingested}  Skipped: {$skipped}  Files: ".count($files));

        return self::SUCCESS;
    }

    private function fromGmail(EmailIngestionService $ingestion, JobEmailParser $parser): int
    {
        $oauth = app(GoogleOAuthService::class);

        // Prefer a connected user's OAuth tokens (auto-refreshed). Fall back to
        // a static GMAIL_ACCESS_TOKEN for one-off/manual runs.
        $user = User::whereNotNull('gmail_refresh_token')->first();
        $token = $user ? $oauth->freshAccessToken($user) : null;
        $token ??= (string) config('services.gmail.access_token');

        if ($token === '') {
            // Not an error for a scheduled run — just nothing to do yet.
            $this->warn('No Gmail connection found. Connect via the dashboard, or set GMAIL_ACCESS_TOKEN.');

            return self::SUCCESS;
        }

        $client = new GmailClient($token);
        $ingested = 0;
        $skipped = 0;
        $scanned = 0;

        foreach ($client->messages((int) $this->option('max')) as $message) {
            $scanned++;
            $parsed = $parser->parse(
                messageId: $message['id'],
                from: $message['from'],
                subject: $message['subject'],
                date: $message['date'],
                body: $message['body'],
            );

            if ($this->option('dry-run')) {
                $this->line(sprintf(
                    '  [dry-run] %s → provider=%s status=%s company=%s',
                    mb_substr($message['subject'] ?? $message['id'], 0, 60),
                    $parsed->provider,
                    $parsed->status ?? '-',
                    $parsed->company ?? '-',
                ));

                continue;
            }

            $ledger = $ingestion->ingest($parsed);
            $ledger === null ? $skipped++ : $ingested++;
        }

        $this->newLine();
        $this->line("Scanned: {$scanned}  Ingested: {$ingested}  Skipped: {$skipped}");

        return self::SUCCESS;
    }
}
