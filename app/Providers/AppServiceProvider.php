<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (self::shouldProhibitDestructiveCommands()) {
            DB::prohibitDestructiveCommands();
        }
    }

    /**
     * Whether destructive migration commands must be blocked.
     *
     * The local `.env` and the production container point at the same MySQL
     * database, so a `migrate:fresh --force` run from the host silently wipes
     * production. Blocking `db:wipe`, `migrate:fresh|refresh|reset|rollback`
     * turns that footgun into a hard error unless the operator explicitly opts
     * in via GAWETRACKER_ALLOW_DESTRUCTIVE_DB.
     *
     * SQLite (the test suite) and any other isolated database are unaffected.
     */
    public static function shouldProhibitDestructiveCommands(): bool
    {
        if (config('gawetracker.allow_destructive')) {
            return false;
        }

        $connection = DB::getDefaultConnection();

        if (config("database.connections.{$connection}.driver") !== 'mysql') {
            return false;
        }

        return config("database.connections.{$connection}.database") === config('gawetracker.shared_database');
    }
}
