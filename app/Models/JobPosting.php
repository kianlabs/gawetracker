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
        'posted_at',
        'job_application_id',
    ];

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
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
}
