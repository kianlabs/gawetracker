<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\JobApplication;
use App\Support\Company\CompanyNameNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyNameNormalizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_legal_forms_and_casing_collapse_to_the_same_key(): void
    {
        $expected = CompanyNameNormalizer::key('Tokopedia');

        $this->assertSame($expected, CompanyNameNormalizer::key('PT Tokopedia'));
        $this->assertSame($expected, CompanyNameNormalizer::key('Tokopedia, PT'));
        $this->assertSame($expected, CompanyNameNormalizer::key('tokopedia.com'));
        $this->assertSame($expected, CompanyNameNormalizer::key('  TOKOPEDIA  '));
        $this->assertSame($expected, CompanyNameNormalizer::key('PT. Tokopedia Tbk'));
    }

    public function test_different_companies_do_not_collide(): void
    {
        $this->assertNotSame(
            CompanyNameNormalizer::key('Tokopedia'),
            CompanyNameNormalizer::key('Traveloka'),
        );
    }

    public function test_empty_or_unusable_names_return_an_empty_key(): void
    {
        $this->assertSame('', CompanyNameNormalizer::key(null));
        $this->assertSame('', CompanyNameNormalizer::key(''));
        $this->assertSame('', CompanyNameNormalizer::key('   '));
        $this->assertSame('', CompanyNameNormalizer::key('PT'));
    }

    public function test_same_helper_compares_across_spellings(): void
    {
        $this->assertTrue(CompanyNameNormalizer::same('PT Tokopedia', 'tokopedia.com'));
        $this->assertFalse(CompanyNameNormalizer::same('Tokopedia', 'Traveloka'));
        $this->assertFalse(CompanyNameNormalizer::same('', ''));
    }

    public function test_find_or_create_is_idempotent_across_spellings(): void
    {
        $a = Company::findOrCreateByName('PT Tokopedia');
        $b = Company::findOrCreateByName('Tokopedia, PT');
        $c = Company::findOrCreateByName('tokopedia.com');

        $this->assertNotNull($a);
        $this->assertSame($a->id, $b->id);
        $this->assertSame($a->id, $c->id);
        $this->assertSame(1, Company::count());
        $this->assertSame('PT Tokopedia', $a->name);
    }

    public function test_find_or_create_returns_null_for_unusable_names(): void
    {
        $this->assertNull(Company::findOrCreateByName('PT'));
        $this->assertNull(Company::findOrCreateByName(''));
        $this->assertSame(0, Company::count());
    }

    public function test_domain_is_enriched_when_first_seen_empty(): void
    {
        $company = Company::findOrCreateByName('Tokopedia');

        $this->assertNull($company->domain);

        $enriched = Company::findOrCreateByName('PT Tokopedia', 'tokopedia.com');

        $this->assertSame($company->id, $enriched->id);
        $this->assertSame('tokopedia.com', $enriched->fresh()->domain);
    }

    /**
     * Regression: the relation must resolve `company_id`, not the
     * `company_record_id` Laravel infers from the method name. Before the fix
     * every application's canonical company silently came back null.
     */
    public function test_application_company_record_relation_resolves_via_company_id(): void
    {
        $company = Company::findOrCreateByName('PT Infomedia Nusantara');

        $application = JobApplication::create([
            'company' => 'Infomedia Nusantara',
            'company_id' => $company->id,
            'position' => 'Back End Developer',
            'applied_at' => now()->toDateString(),
            'status' => 'wishlist',
        ]);

        $this->assertNotNull($application->fresh()->companyRecord);
        $this->assertSame($company->id, $application->fresh()->companyRecord->id);
    }
}
