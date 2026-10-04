<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Structured salary on job postings.
 *
 * `salary_note` keeps the board's original prose; these columns hold the parsed
 * numbers so the discovery list can sort and filter by pay instead of treating
 * salary as an opaque string.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->unsignedBigInteger('salary_min')->nullable()->after('salary_note');
            $table->unsignedBigInteger('salary_max')->nullable()->after('salary_min');
            $table->string('salary_currency', 3)->nullable()->after('salary_max');
            $table->string('salary_period', 16)->nullable()->after('salary_currency');

            $table->index('salary_min');
        });
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropIndex(['salary_min']);
            $table->dropColumn(['salary_min', 'salary_max', 'salary_currency', 'salary_period']);
        });
    }
};
