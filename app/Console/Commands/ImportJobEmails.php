<?php

namespace App\Console\Commands;

use App\Console\Concerns\ResolvesCommandUser;
use App\Models\User;
use App\Services\EmailIngestionService;
use App\Services\GoogleOAuthService;
use App\Support\Email\GmailClient;
use App\Support\Email\JobEmailParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Ingest JobStreet/Glints application emails into GaweTracker.
 *
 * Two sources:
 *  - a local file/directory of .eml messages (`emails:import <path>`), for
 *    manual imports; the owner is chosen with --user;
 *  - each user's connected Gmail mailbox (`emails:import --gmail`), which is
 *    what the scheduler runs so every account keeps syncing on its own.
 *
 * Gmail mode signs each user into the auth guard in turn, which is what scopes
 * the writes (and the dedup ledger) to that user's rows.
 */
class ImportJobEmails extends Command
{
    use ResolvesCommandUser;

    protected $signature = 'emails:import
        {path? : File or directory of .eml messages to ingest}
        {--gmail : Pull from each connected user\'s Gmail instead of a local path}
        {--max=100 : Maximum number of Gmail messages to scan per user}
        {--dry-run : Parse and report without writing to the database}
        {--user= : Owning user (email or id); defaults to the first account}';

    protected $description = 'Ingest JobStreet/Glints application emails into GaweTracker';

    public function handle(EmailIngestionService $ingestion, JobEmailParser $parser): int
    {
        if ($this->option('gmail')) {
            return $this->fromGmail($ingestion, $parser);
        }

        if (! $this->option('dry-run') && $this->resolveUser() === null) {
            return self::FAILURE;
        }

        return $this->fromPath($ingestion, $parser);
    }

    private function fromPath(EmailIngestionService $ingestion, JobEmailParser $parser): int
    {
        $path = $this->argument('path');
        if (! $path || (! is_file($path) && ! is_dir($path))) {
            $this->error('Provide a file or directory path as the first argument (or use --gmail).');

            return self::FAILURE;
        }

        $files = is_dir($path)
            ? glob(rtrim($path, '/').'/*.{eml,txt}', GLOB_BRACE) ?: []
            : [$path];

        $ingested = 0;
        $skipped = 0;

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                $this->warn("  Could not read {$file}");

                continue;
            }

            $parsed = $parser->parseRaw($content);

            if ($this->option('dry-run')) {
                $this->line(sprintf(
                    '  [dry-run] %s → provider=%s status=%s company=%s',
                    mb_substr(basename($file), 0, 60),
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
        $this->line("Ingested: {$ingested}  Skipped: {$skipped}  Files: ".count($files));

        return self::SUCCESS;
    }

    /**
     * Sync every connected mailbox, one user at a time.
     */
    private function fromGmail(EmailIngestionService $ingestion, JobEmailParser $parser): int
    {
        $oauth = app(GoogleOAuthService::class);

        $users = $this->gmailUsers();
        if ($users->isEmpty()) {
            // Not an error for a scheduled run — just nothing to do yet.
            $this->warn('No Gmail connection found. Connect a mailbox from the dashboard first.');

            return self::SUCCESS;
        }

        $max = (int) $this->option('max');
        $totalIngested = 0;
        $totalSkipped = 0;
        $totalScanned = 0;
        $synced = 0;

        foreach ($users as $user) {
            // Sign the owner in so ingestion writes (and the dedup ledger) are
            // scoped to their rows by the BelongsToUser global scope.
            Auth::setUser($user);

            $token = $oauth->freshAccessToken($user);
            if ($token === null) {
                $this->warn("  {$user->email}: token tidak valid, dilewati (hubungkan ulang).");

                continue;
            }

            $synced++;
            $ingested = 0;
            $skipped = 0;
            $scanned = 0;

            foreach ((new GmailClient($token))->messages($max) as $message) {
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
                        '  [dry-run] %s: %s → provider=%s status=%s company=%s',
                        $user->email,
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

            $totalScanned += $scanned;
            $totalIngested += $ingested;
            $totalSkipped += $skipped;

            $this->line("  {$user->email}: {$scanned} dipindai, {$ingested} baru, {$skipped} dilewati");
        }

        $this->newLine();
        $this->line("Akun disinkron: {$synced}  Dipindai: {$totalScanned}  Baru: {$totalIngested}  Dilewati: {$totalSkipped}");

        return self::SUCCESS;
    }

    /**
     * Connected mailboxes to sync, honouring --user when it is given.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    private function gmailUsers(): \Illuminate\Database\Eloquent\Collection
    {
        $query = User::query()->whereNotNull('gmail_refresh_token');

        $identifier = $this->option('user');
        if ($identifier) {
            $query->where(is_numeric($identifier) ? 'id' : 'email', $identifier);
        }

        return $query->orderBy('id')->get();
    }
}
