<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link every job application to the canonical company row.
     *
     * Nullable so existing rows survive the migration; a backfill command
     * (companies:backfill) populates them from the free-text `company` column.
     */
    public function up(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->foreignId('company_id')
                ->nullable()
                ->after('company')
                ->constrained('companies')
                ->nullOnDelete();

            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
