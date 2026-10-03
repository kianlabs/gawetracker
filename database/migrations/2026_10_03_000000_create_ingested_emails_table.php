<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ledger of inbound emails already processed, so the same confirmation or
     * status email is never ingested twice. Also records which application an
     * email was matched to.
     */
    public function up(): void
    {
        Schema::create('ingested_emails', function (Blueprint $table) {
            $table->id();

            // Message-ID header from the source mailbox — the natural dedup key.
            $table->string('message_id')->unique();

            // Detected source: 'jobstreet', 'glints', or 'unknown'.
            $table->string('provider')->default('unknown');

            $table->string('from_address')->nullable();
            $table->string('subject')->nullable();

            // When the email was received (from the source, not ingestion time).
            $table->timestamp('received_at')->nullable();

            // The pipeline status the parser inferred, or null if unrecognised.
            $table->string('classified_status')->nullable();

            // The application this email was matched to or created (null when the
            // email could not be matched).
            $table->foreignId('job_application_id')
                ->nullable()
                ->constrained('job_applications')
                ->nullOnDelete();

            // Short excerpt kept for auditing / manual review.
            $table->text('raw_snippet')->nullable();

            $table->timestamps();

            $table->index('provider');
            $table->index('classified_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingested_emails');
    }
};
