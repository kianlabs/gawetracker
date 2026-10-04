<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Support\Company\CompanyNameNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_find_or_create_recovers_when_a_concurrent_insert_wins_the_race(): void
    {
        $key = CompanyNameNormalizer::key('Race Probe Company');
        $armed = true;

        // Reproduce the race window: a second worker inserts the same company
        // after our SELECT but before our INSERT. The unique index on
        // normalized_key then rejects the loser's insert.
        Company::creating(function (Company $model) use (&$armed, $key): void {
            if (! $armed || $model->normalized_key !== $key) {
                return;
            }

            $armed = false;

            DB::table('companies')->insert([
                'normalized_key' => $key,
                'name' => 'Concurrent Winner',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $company = Company::findOrCreateByName('Race Probe Company');

        $this->assertNotNull($company);
        $this->assertSame('Concurrent Winner', $company->name);
        $this->assertSame(1, Company::where('normalized_key', $key)->count());
    }
}
