<?php

namespace App\Console\Commands;

use App\Services\Discovery\GlintsSource;
use App\Services\Discovery\JobDiscoveryService;
use App\Services\Discovery\JobstreetSource;
use Illuminate\Console\Command;

class DiscoverJobs extends Command
{
    protected $signature = 'jobs:discover
        {keyword : Search keyword, e.g. "backend" or "data analyst"}
        {--limit=30 : Maximum postings to pull per source}';

    protected $description = 'Discover job postings from Glints and Jobstreet into GaweTracker';

    public function handle(): int
    {
        $keyword = (string) $this->argument('keyword');
        $limit = (int) $this->option('limit');

        $service = new JobDiscoveryService([
            new GlintsSource(),
            new JobstreetSource(),
        ]);

        $this->info("Mencari \"{$keyword}\" di Glints & Jobstreet...");

        $result = $service->discover($keyword, $limit);

        $this->newLine();
        $this->line("Dilihat: {$result['seen']}  Baru: {$result['created']}  Diperbarui: {$result['updated']}");

        foreach ($result['errors'] as $error) {
            $this->warn("  ⚠ {$error}");
        }

        return self::SUCCESS;
    }
}
