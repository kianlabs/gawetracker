<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Re-add the Gmail OAuth columns that back the per-user auto-sync.
     *
     * The columns were dropped when the feature was briefly removed; they are
     * restored here. The guard makes the migration a no-op on databases where
     * an earlier migration in this series already created them, so replaying
     * the whole chain from scratch stays valid.
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'gmail_refresh_token')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->text('gmail_access_token')->nullable()->after('remember_token');
            $table->text('gmail_refresh_token')->nullable()->after('gmail_access_token');
            $table->timestamp('gmail_token_expires_at')->nullable()->after('gmail_refresh_token');
            $table->string('gmail_email')->nullable()->after('gmail_token_expires_at');
            $table->timestamp('gmail_connected_at')->nullable()->after('gmail_email');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'gmail_refresh_token')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'gmail_access_token',
                'gmail_refresh_token',
                'gmail_token_expires_at',
                'gmail_email',
                'gmail_connected_at',
            ]);
        });
    }
};
