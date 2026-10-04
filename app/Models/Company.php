<?php

namespace App\Models;

use App\Support\Company\CompanyNameNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Canonical employer. Acts as the single matching target for every source
 * (email ingestion, Glints, Jobstreet) so the same company is never duplicated.
 */
class Company extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'normalized_key',
        'name',
        'domain',
    ];

    public function jobApplications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    /**
     * Find an existing company by any spelling of its name, or create one.
     *
     * Returns null when the name has no usable key (e.g. empty string), so
     * callers can keep the free-text value without forcing a junk row.
     */
    public static function findOrCreateByName(?string $name, ?string $domain = null): ?self
    {
        $key = CompanyNameNormalizer::key($name);

        if ($key === '') {
            return null;
        }

        $company = static::where('normalized_key', $key)->first();

        if ($company !== null) {
            // Enrich a missing domain if we now know it.
            if ($domain !== null && $company->domain === null) {
                $company->domain = $domain;
                $company->save();
            }

            return $company;
        }

        return static::create([
            'normalized_key' => $key,
            'name' => CompanyNameNormalizer::display($name),
            'domain' => $domain,
        ]);
    }
}
