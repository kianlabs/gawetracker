<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Short description for a discovered posting.
 *
 * Boards expose this in their search payload (Jobstreet's `teaser`, Glints'
 * `descriptionJsonString`), so we capture it at discovery time and can show a
 * preview without a second per-job request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->text('description')->nullable()->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
