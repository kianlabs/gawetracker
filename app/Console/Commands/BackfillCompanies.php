<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\JobApplication;
use App\Support\Company\CompanyNameNormalizer;
use Illuminate\Console\Command;

class BackfillCompanies extends Command
{
    protected $signature = 'companies:backfill
        {--dry-run : Report what would change without writing}';

    protected $description = 'Link existing job applications to canonical company rows';

    public function handle(): int
    {
        $apps = JobApplication::whereNull('company_id')->get();
        $linked = 0;
        $skipped = 0;

        foreach ($apps as $app) {
            $key = CompanyNameNormalizer::key($app->company);

            if ($key === '') {
                $skipped++;
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line(sprintf('  [dry-run] #%d %s → %s', $app->id, $app->company, $key));
                $linked++;
                continue;
            }

            $company = Company::findOrCreateByName($app->company);
            $app->company_id = $company?->id;
            $app->save();
            $linked++;
        }

        $this->newLine();
        $this->line("Linked: {$linked}  Skipped: {$skipped}  Total scanned: ".$apps->count());

        return self::SUCCESS;
    }
}
