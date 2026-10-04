<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Job postings discovered from external boards (Glints, Jobstreet).
     *
     * These are candidates, separate from job_applications: a posting is what a
     * board advertises, an application is something the user decided to pursue.
     * The unique (source, external_id) pair makes re-running discovery
     * idempotent.
     */
    public function up(): void
    {
        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();

            $table->string('source');       // 'glints' | 'jobstreet'
            $table->string('external_id');  // the board's own id

            $table->string('title');
            $table->string('company');
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();

            $table->string('location')->nullable();
            $table->string('source_url')->nullable();
            $table->string('salary_note')->nullable();
            $table->timestamp('posted_at')->nullable();

            // Set once the user promotes this posting into a real application.
            $table->foreignId('job_application_id')->nullable()->constrained('job_applications')->nullOnDelete();

            $table->timestamps();

            $table->unique(['source', 'external_id']);
            $table->index('company_id');
            $table->index('posted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_postings');
    }
};
