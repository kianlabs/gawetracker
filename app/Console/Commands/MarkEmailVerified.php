<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Mark an existing account's email as verified.
 *
 * Accounts created before verification existed (or before a real mailer was
 * configured) have email_verified_at = NULL. Turning verification on would lock
 * them out, so this command backfills those rows. It only writes the timestamp;
 * nothing else about the account is touched.
 *
 * Pass no argument to list the accounts and their current state.
 */
class MarkEmailVerified extends Command
{
    protected $signature = 'email:mark-verified
        {email? : The account email to mark verified (omit to list accounts)}';

    protected $description = 'Mark an existing account email as verified (recovery/backfill)';

    public function handle(): int
    {
        $email = $this->argument('email');

        if ($email === null) {
            return $this->listAccounts();
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("Akun \"{$email}\" tidak ditemukan.");

            return self::FAILURE;
        }

        if ($user->hasVerifiedEmail()) {
            $this->info("Akun \"{$email}\" sudah terverifikasi ({$user->email_verified_at}).");

            return self::SUCCESS;
        }

        $user->markEmailAsVerified();

        $this->info("Akun \"{$email}\" berhasil ditandai terverifikasi.");

        return self::SUCCESS;
    }

    /**
     * Show every account and whether it is verified, so the operator can see
     * who would be affected before flipping EMAIL_VERIFICATION_ENABLED on.
     */
    private function listAccounts(): int
    {
        $users = User::query()->orderBy('id')->get(['id', 'email', 'email_verified_at']);

        if ($users->isEmpty()) {
            $this->error('Belum ada pengguna.');

            return self::FAILURE;
        }

        $this->table(
            ['ID', 'Email', 'Terverifikasi'],
            $users->map(fn (User $user) => [
                $user->id,
                $user->email,
                $user->hasVerifiedEmail() ? 'ya' : 'BELUM',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
