<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make the tracker multi-user.
 *
 * Every record a user creates gets an owner. The pre-existing single-user data
 * is handed to the first account so nothing is orphaned. `companies` stays
 * global on purpose: it is a canonical employer registry shared by everyone, so
 * "PT Tokopedia" is one row no matter who searched for it.
 */
return new class extends Migration
{
    /** Tables that gain an owning `user_id`. */
    private const TABLES = [
        'job_applications',
        'job_postings',
        'status_histories',
        'interview_checklists',
        'offer_details',
        'ingested_emails',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t): void {
                $t->foreignId('user_id')->nullable()->after('id')
                    ->constrained()->cascadeOnDelete();
            });
        }

        // Hand all existing rows to the first account, if there is one.
        $ownerId = DB::table('users')->min('id');
        if ($ownerId !== null) {
            foreach (self::TABLES as $table) {
                DB::table($table)->whereNull('user_id')->update(['user_id' => $ownerId]);
            }
        }

        // Uniqueness must be per-user now: two people may each discover the same
        // board posting, or ingest the same .eml.
        Schema::table('job_postings', function (Blueprint $t): void {
            $t->dropUnique(['source', 'external_id']);
            $t->unique(['user_id', 'source', 'external_id']);
        });

        Schema::table('ingested_emails', function (Blueprint $t): void {
            $t->dropUnique(['message_id']);
            $t->unique(['user_id', 'message_id']);
        });
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $t): void {
            $t->dropUnique(['user_id', 'source', 'external_id']);
            $t->unique(['source', 'external_id']);
        });

        Schema::table('ingested_emails', function (Blueprint $t): void {
            $t->dropUnique(['user_id', 'message_id']);
            $t->unique(['message_id']);
        });

        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t): void {
                $t->dropConstrainedForeignId('user_id');
            });
        }
    }
};
