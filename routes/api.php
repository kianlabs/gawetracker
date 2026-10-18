<?php

use App\Http\Controllers\Api\BotApplicationController;
use App\Http\Middleware\ValidateBotApiToken;
use Illuminate\Support\Facades\Route;

Route::middleware([ValidateBotApiToken::class])->group(function () {
    // Healthcheck & bot connection status
    Route::get('/bot/status', [BotApplicationController::class, 'status'])->name('api.bot.status');

    // Webhook ingestion for bot & extension applied jobs
    Route::post('/bot/applications', [BotApplicationController::class, 'store'])->name('api.bot.applications.store');

    // Semantic alias for generic external appliers
    Route::post('/applications/external', [BotApplicationController::class, 'store'])->name('api.applications.external');
});
