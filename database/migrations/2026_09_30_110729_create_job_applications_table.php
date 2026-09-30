<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->string('company');
            $table->string('position');
            $table->string('location')->nullable();
            $table->enum('work_type', ['remote', 'onsite', 'hybrid'])->nullable();
            $table->string('source')->nullable();
            $table->string('source_url')->nullable();
            $table->date('applied_at');
            $table->string('salary_note')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_info')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['wishlist', 'applied', 'screening', 'interview', 'offer', 'hired', 'rejected'])->default('wishlist');
            $table->timestamp('last_status_change_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};
