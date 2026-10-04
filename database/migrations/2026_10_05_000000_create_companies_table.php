<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Canonical employer registry. Every job application (and every discovered
     * job posting) points at one company row, so "PT Tokopedia", "Tokopedia"
     * and "tokopedia.com" cannot create three separate entries.
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();

            // Normalised comparison key (see CompanyNameNormalizer::key).
            // Unique so the same employer can only ever be inserted once.
            $table->string('normalized_key')->unique();

            // Human-facing label, original casing (e.g. "PT Tokopedia").
            $table->string('name');

            // Optional canonical website / domain, when known.
            $table->string('domain')->nullable();

            $table->timestamps();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
