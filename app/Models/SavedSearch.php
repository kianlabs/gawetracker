<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A keyword search the scheduler re-runs periodically, owned by one user.
 */
class SavedSearch extends Model
{
    use BelongsToUser;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'keyword',
        'limit',
        'is_active',
        'last_run_at',
        'last_created',
        'last_seen',
    ];

    protected function casts(): array
    {
        return [
            'limit' => 'integer',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
            'last_created' => 'integer',
            'last_seen' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
