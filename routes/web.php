<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InterviewChecklistController;
use App\Http\Controllers\JobApplicationController;
use App\Http\Controllers\KanbanController;
use App\Http\Controllers\OfferController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : view('welcome');
})->name('welcome');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Applications specialized routes (must be defined before resource route)
    Route::get('/applications/kanban', [KanbanController::class, 'index'])->name('applications.kanban');
    Route::get('/applications/export', [JobApplicationController::class, 'exportCsv'])->name('applications.export');
    Route::post('/applications/quick', [JobApplicationController::class, 'quickStore'])->name('applications.quick-store');

    Route::post('/applications/{application}/quick-status', [JobApplicationController::class, 'quickStatus'])->name('applications.quick-status');
    Route::post('/applications/{application}/histories', [JobApplicationController::class, 'addHistoryNote'])->name('applications.histories.store');

    // Checklist routes
    Route::post('/applications/{application}/checklists', [InterviewChecklistController::class, 'store'])->name('applications.checklists.store');
    Route::patch('/checklists/{checklist}/toggle', [InterviewChecklistController::class, 'toggle'])->name('checklists.toggle');
    Route::delete('/checklists/{checklist}', [InterviewChecklistController::class, 'destroy'])->name('checklists.destroy');

    // Offer comparison routes
    Route::get('/offers', [OfferController::class, 'index'])->name('offers.index');
    Route::post('/applications/{application}/offers', [OfferController::class, 'storeOrUpdate'])->name('offers.save');

    // Analytics routes
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

    // Core applications resource
    Route::resource('applications', JobApplicationController::class);
});
