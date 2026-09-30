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
        Schema::create('offer_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_application_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('base_salary')->nullable();
            $table->string('salary_period')->default('monthly');
            $table->string('thr')->nullable();
            $table->string('bonus')->nullable();
            $table->string('allowance')->nullable();
            $table->string('health_insurance')->nullable();
            $table->string('work_scheme')->nullable();
            $table->date('deadline_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offer_details');
    }
};
