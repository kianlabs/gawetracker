<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Scopes a model to its owning user.
 *
 * Multi-user safety has two halves:
 *  - every query is filtered to the authenticated user, so a forgotten
 *    `where('user_id', ...)` can never leak another account's rows;
 *  - every insert is stamped with that user's id automatically.
 *
 * The scope only activates while a user is authenticated. Console commands and
 * tests that run without a session therefore keep their unscoped, global
 * behaviour, and the CLI can opt in per user with `Auth::setUser()`.
 */
trait BelongsToUser
{
    protected static function bootBelongsToUser(): void
    {
        static::addGlobalScope('user', function (Builder $builder): void {
            if (Auth::check()) {
                $builder->where(
                    $builder->getModel()->getTable().'.user_id',
                    Auth::id(),
                );
            }
        });

        static::creating(function ($model): void {
            if (Auth::check() && $model->getAttribute('user_id') === null) {
                $model->setAttribute('user_id', Auth::id());
            }
        });
    }

    /**
     * The user this record belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
