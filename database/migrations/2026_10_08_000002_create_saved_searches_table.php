<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A saved job search that the scheduler re-runs on the user's behalf.
 *
 * Each row is one keyword (optionally scoped to a source) with its own result
 * limit and a small run ledger so the UI can show when it last ran and how much
 * it turned up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('keyword');
            $table->unsignedSmallInteger('limit')->default(30);
            $table->boolean('is_active')->default(true);

            // Run ledger: when it last ran and what the last run produced.
            $table->timestamp('last_run_at')->nullable();
            $table->unsignedInteger('last_created')->default(0);
            $table->unsignedInteger('last_seen')->default(0);

            $table->timestamps();

            // One saved search per keyword per user.
            $table->unique(['user_id', 'keyword']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_searches');
    }
};
