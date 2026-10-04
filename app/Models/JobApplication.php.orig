<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class JobApplication extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company',
        'company_id',
        'position',
        'location',
        'work_type',
        'source',
        'source_url',
        'applied_at',
        'salary_note',
        'contact_name',
        'contact_info',
        'notes',
        'interview_result',
        'status',
        'last_status_change_at',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'wishlist',
    ];

    /**
     * All valid pipeline statuses, in funnel order. `rejected` is terminal but
     * kept here so it is accepted anywhere a status is validated.
     *
     * @var list<string>
     */
    public const STATUSES = [
        'wishlist',
        'applied',
        'screening',
        'interview',
        'offer',
        'hired',
        'rejected',
    ];

    /**
     * Statuses that count as "still in the pipeline" (active). Everything except
     * the terminal states `hired` and `rejected`.
     *
     * @var list<string>
     */
    public const ACTIVE_STATUSES = [
        'wishlist',
        'applied',
        'screening',
        'interview',
        'offer',
    ];

    /**
     * Scope to applications whose current status is still active (not hired or
     * rejected). Use this everywhere "Aktif Diproses" is shown so the metric has
     * a single, consistent definition.
     *
     * @param  Builder<JobApplication>  $query
     * @return Builder<JobApplication>
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'applied_at' => 'date',
            'last_status_change_at' => 'datetime',
        ];
    }

    /**
     * Get the canonical company this application belongs to.
     *
     * Named `companyRecord` (not `company`) because `company` is already a
     * free-text column on this table that views read directly. The foreign key
     * is passed explicitly — Laravel would otherwise infer `company_record_id`
     * from the method name and silently return null on every lookup.
     */
    public function companyRecord(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Get the status histories for the job application.
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(StatusHistory::class);
    }

    /**
     * Get the interview checklist items for the job application.
     */
    public function interviewChecklists(): HasMany
    {
        return $this->hasMany(InterviewChecklist::class);
    }

    /**
     * Get the offer details for the job application.
     */
    public function offerDetail(): HasOne
    {
        return $this->hasOne(OfferDetail::class);
    }

    /**
     * Returns a Google Favicon URL when the company domain is known
     * (either from source_url or the built-in map), null otherwise.
     * Null signals views to render the initial-letter badge instead.
     */
    public function logoUrl(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                $domain = $this->resolveDomain();

                return $domain
                    ? "https://www.google.com/s2/favicons?domain={$domain}&sz=64"
                    : null;
            }
        );
    }

    private function resolveDomain(): ?string
    {
        // Use source_url domain when it's the actual company site, not a job board
        if ($this->source_url) {
            $host = parse_url($this->source_url, PHP_URL_HOST);
            if ($host) {
                $jobBoards = ['linkedin.com', 'jobstreet.co.id', 'glints.com', 'kalibrr.com', 'indeed.com'];
                $isJobBoard = collect($jobBoards)->contains(fn ($b) => str_contains($host, $b));
                if (! $isJobBoard) {
                    return preg_replace('/^www\./', '', $host);
                }
            }
        }

        // Known Indonesian tech companies
        $map = [
            'tokopedia' => 'tokopedia.com',
            'goto' => 'gotogroup.com',
            'gojek' => 'gojek.com',
            'traveloka' => 'traveloka.com',
            'shopee' => 'shopee.co.id',
            'blibli' => 'blibli.com',
            'bukalapak' => 'bukalapak.com',
            'tiket' => 'tiket.com',
            'dana' => 'dana.id',
            'ovo' => 'ovo.id',
            'xendit' => 'xendit.co',
            'midtrans' => 'midtrans.com',
            'koinworks' => 'koinworks.com',
            'kredivo' => 'kredivo.com',
            'ajaib' => 'ajaib.co.id',
            'stockbit' => 'stockbit.com',
            'ruangguru' => 'ruangguru.com',
            'zenius' => 'zenius.net',
            'vidio' => 'vidio.com',
            'grab' => 'grab.com',
            'sea' => 'sea.com',
        ];

        $nameLower = strtolower($this->company);
        foreach ($map as $keyword => $domain) {
            if (str_contains($nameLower, $keyword)) {
                return $domain;
            }
        }

        // Unknown company — let the view show the initial-letter badge
        return null;
    }
}
