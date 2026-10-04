<?php

namespace App\Console\Commands;

use App\Models\SavedSearch;
use App\Models\User;
use App\Services\Discovery\GlintsSource;
use App\Services\Discovery\JobDiscoveryService;
use App\Services\Discovery\JobstreetSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Re-run every active saved search so the discovery list keeps filling itself
 * without the user pressing "Cari" again.
 *
 * Multi-user aware: each search runs with its owner signed into the auth guard,
 * which is what scopes the write to that user's rows. Without --user we walk
 * every account, one at a time, so a single tenant is never written by another.
 */
class DiscoverSavedSearches extends Command
{
    protected $signature = 'jobs:discover-saved
        {--user= : Only run this user\'s saved searches (email or id)}
        {--limit= : Override the per-search result limit}';

    protected $description = 'Re-run saved job searches and persist new postings';

    public function handle(): int
    {
        $service = new JobDiscoveryService([
            new GlintsSource,
            new JobstreetSource,
        ]);

        $override = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        $users = $this->targetUsers();

        if ($users === null) {
            return self::FAILURE;
        }

        $totalCreated = 0;
        $totalSeen = 0;
        $ran = 0;

        foreach ($users as $user) {
            // Signing the owner in activates the BelongsToUser scope, so the
            // query below and every write it triggers stay inside this account.
            Auth::setUser($user);

            $searches = SavedSearch::query()->where('is_active', true)->orderBy('id')->get();

            foreach ($searches as $search) {
                $limit = $override ?? $search->limit;
                $result = $service->discover($search->keyword, $limit);

                $search->forceFill([
                    'last_run_at' => now(),
                    'last_created' => $result['created'],
                    'last_seen' => $result['seen'],
                ])->save();

                $totalCreated += $result['created'];
                $totalSeen += $result['seen'];
                $ran++;

                $this->line(sprintf(
                    '  %s — "%s": %d baru dari %d hasil',
                    $user->email,
                    $search->keyword,
                    $result['created'],
                    $result['seen'],
                ));

                foreach ($result['errors'] as $error) {
                    $this->warn("    ⚠ {$error}");
                }
            }
        }

        $this->newLine();
        $this->line("Pencarian dijalankan: {$ran}  Lowongan baru: {$totalCreated}  Dilihat: {$totalSeen}");

        return self::SUCCESS;
    }

    /**
     * The users whose saved searches should run, or null after reporting why not.
     *
     * @return iterable<int, User>|null
     */
    private function targetUsers(): ?iterable
    {
        $identifier = $this->option('user');

        if ($identifier === null) {
            $users = User::query()->orderBy('id')->get();

            if ($users->isEmpty()) {
                $this->error('Belum ada pengguna. Buat akun terlebih dahulu.');

                return null;
            }

            return $users;
        }

        $user = User::query()
            ->where(is_numeric($identifier) ? 'id' : 'email', $identifier)
            ->first();

        if ($user === null) {
            $this->error("Pengguna \"{$identifier}\" tidak ditemukan.");

            return null;
        }

        return [$user];
    }
}
