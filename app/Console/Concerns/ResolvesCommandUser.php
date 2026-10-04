<?php

namespace App\Console\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Console commands have no session, so they must be told whose data they are
 * touching. This resolves the `--user=` option (email or id) and signs that
 * user into the auth guard, which is what activates the BelongsToUser global
 * scope and its create-time stamping.
 */
trait ResolvesCommandUser
{
    /**
     * Resolve and authenticate the target user, defaulting to the first account.
     *
     * Returns null (after reporting the problem) when the requested user cannot
     * be found or no accounts exist at all.
     */
    protected function resolveUser(): ?User
    {
        $identifier = $this->option('user');

        $user = $identifier
            ? User::query()
                ->where(is_numeric($identifier) ? 'id' : 'email', $identifier)
                ->first()
            : User::query()->orderBy('id')->first();

        if ($user === null) {
            $this->error($identifier
                ? "Pengguna \"{$identifier}\" tidak ditemukan."
                : 'Belum ada pengguna. Buat akun terlebih dahulu atau gunakan --user=.');

            return null;
        }

        Auth::setUser($user);

        return $user;
    }
}
