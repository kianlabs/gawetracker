<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\GmailConnectionController;
use App\Http\Controllers\InterviewChecklistController;
use App\Http\Controllers\JobApplicationController;
use App\Http\Controllers\JobDiscoveryController;
use App\Http\Controllers\KanbanController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\PublicPageController;
use Illuminate\Support\Facades\Route;

// Publicly reachable pages. Google's OAuth brand verification requires a public
// home page, privacy policy and terms on the OAuth client's domain.
Route::get('/about', [PublicPageController::class, 'about'])->name('about');
Route::get('/privacy', [PublicPageController::class, 'privacy'])->name('privacy');
Route::get('/terms', [PublicPageController::class, 'terms'])->name('terms');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    // Logout must stay reachable even when the email is unverified.
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Email verification endpoints. They are always registered, but nothing
    // links to them while EMAIL_VERIFICATION_ENABLED is off, and the app routes
    // below never redirect there in that mode.
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')->name('verification.send');

    // Gmail mailbox linking (read-only). Kept outside the verified group so a
    // user can connect their mailbox even before confirming their email.
    Route::get('/gmail/connect', [GmailConnectionController::class, 'redirect'])->name('gmail.connect');
    Route::get('/gmail/callback', [GmailConnectionController::class, 'callback'])->name('gmail.callback');
    Route::delete('/gmail/disconnect', [GmailConnectionController::class, 'destroy'])->name('gmail.disconnect');

    // The actual app. `verified.when-enabled` is a no-op while verification is
    // switched off and enforces a verified email once it is switched on.
    Route::middleware('verified.when-enabled')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Weekly target persistence (per-user preference)
        Route::post('/dashboard/target', [DashboardController::class, 'updateTarget'])->name('dashboard.target.update');

        // Job discovery from external boards (Glints, Jobstreet)
        Route::get('/discovery', [JobDiscoveryController::class, 'index'])->name('discovery.index');
        Route::post('/discovery/search', [JobDiscoveryController::class, 'search'])->name('discovery.search');
        Route::post('/discovery/saved', [JobDiscoveryController::class, 'storeSavedSearch'])->name('discovery.saved.store');
        Route::delete('/discovery/saved/{savedSearch}', [JobDiscoveryController::class, 'destroySavedSearch'])->name('discovery.saved.destroy');
        Route::post('/discovery/{posting}/promote', [JobDiscoveryController::class, 'promote'])->name('discovery.promote');
        Route::delete('/discovery', [JobDiscoveryController::class, 'destroyAll'])->name('discovery.destroy-all');
        Route::delete('/discovery/{posting}', [JobDiscoveryController::class, 'destroy'])->name('discovery.destroy');

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
});
