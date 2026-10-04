<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A job posting discovered from an external board (Glints, Jobstreet).
 *
 * Distinct from JobApplication: a posting is an advertisement, an application
 * is a decision. Promoting a posting creates/links a JobApplication.
 */
class JobPosting extends Model
{
    use BelongsToUser;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'source',
        'external_id',
        'title',
        'company',
        'company_id',
        'location',
        'source_url',
        'salary_note',
        'salary_min',
        'salary_max',
        'salary_currency',
        'salary_period',
        'posted_at',
        'job_application_id',
    ];

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
            'salary_min' => 'integer',
            'salary_max' => 'integer',
        ];
    }

    public function companyRecord(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class);
    }

    /**
     * Whether this posting has already been turned into an application.
     */
    public function isPromoted(): bool
    {
        return $this->job_application_id !== null;
    }

    /**
     * Human-readable salary range built from the parsed columns, e.g.
     * "IDR 10.000.000 – 15.000.000 /bulan". Falls back to the board's original
     * prose when we could not parse the numbers.
     */
    public function salaryLabel(): ?string
    {
        if ($this->salary_min === null && $this->salary_max === null) {
            return $this->salary_note;
        }

        $prefix = $this->salary_currency ? $this->salary_currency.' ' : '';

        if ($this->salary_min !== null && $this->salary_max !== null && $this->salary_min !== $this->salary_max) {
            $range = number_format($this->salary_min, 0, ',', '.').' – '.number_format($this->salary_max, 0, ',', '.');
        } else {
            $single = $this->salary_min ?? $this->salary_max;
            $range = number_format($single, 0, ',', '.');
        }

        $suffix = match ($this->salary_period) {
            'monthly' => ' /bulan',
            'yearly' => ' /tahun',
            'daily' => ' /hari',
            'hourly' => ' /jam',
            default => '',
        };

        return $prefix.$range.$suffix;
    }
}
