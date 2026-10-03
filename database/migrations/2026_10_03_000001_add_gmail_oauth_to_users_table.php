<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store the per-user Gmail OAuth connection. Tokens are encrypted at the
     * model layer (see User::$casts), so this table only holds ciphertext.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('gmail_access_token')->nullable()->after('password');
            $table->text('gmail_refresh_token')->nullable()->after('gmail_access_token');
            $table->timestamp('gmail_token_expires_at')->nullable()->after('gmail_refresh_token');
            $table->string('gmail_email')->nullable()->after('gmail_token_expires_at');
            $table->timestamp('gmail_connected_at')->nullable()->after('gmail_email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'gmail_access_token',
                'gmail_refresh_token',
                'gmail_token_expires_at',
                'gmail_email',
                'gmail_connected_at',
            ]);
        });
    }
};
