<?php

namespace App\Console\Commands;

use App\Services\EmailIngestionService;
use App\Support\Email\JobEmailParser;
use Illuminate\Console\Command;

class ImportJobEmails extends Command
{
    protected $signature = 'emails:import
        {path? : File or directory of .eml messages to ingest}
        {--dry-run : Parse and report without writing to the database}';

    protected $description = 'Ingest JobStreet/Glints application emails into GaweTracker';

    public function handle(EmailIngestionService $ingestion, JobEmailParser $parser): int
    {
        return $this->fromPath($ingestion, $parser);
    }

    private function fromPath(EmailIngestionService $ingestion, JobEmailParser $parser): int
    {
        $path = $this->argument('path');
        if (! $path || (! is_file($path) && ! is_dir($path))) {
            $this->error('Provide a file or directory path as the first argument.');

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
}
