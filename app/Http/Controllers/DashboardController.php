<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use App\Models\OfferDetail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the dashboard view with metrics and follow-up reminders.
     */
    public function index(Request $request): View
    {
        // Consolidate all status counts into a single grouped query
        $statusCounts = JobApplication::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $total = $statusCounts->sum();
        $wishlist = $statusCounts['wishlist'] ?? 0;
        $applied = $statusCounts['applied'] ?? 0;
        $screening = $statusCounts['screening'] ?? 0;
        $interview = $statusCounts['interview'] ?? 0;
        $offer = $statusCounts['offer'] ?? 0;
        $hired = $statusCounts['hired'] ?? 0;
        $rejected = $statusCounts['rejected'] ?? 0;

        $active = $statusCounts->only(JobApplication::ACTIVE_STATUSES)->sum();

        $interviewReachedCount = JobApplication::where(function ($query) {
            $query->whereIn('status', ['interview', 'offer', 'hired'])
                ->orWhereHas('statusHistories', function ($q) {
                    $q->whereIn('to_status', ['interview', 'offer', 'hired']);
                });
        })->count();

        $winRate = round(($interviewReachedCount / max(1, $total)) * 100, 1);

        $rawTarget = (int) $request->input('target', $request->user()->weeklyTarget());
        $weeklyTarget = $rawTarget > 0 ? $rawTarget : 8;
        $weeklyCount = JobApplication::whereBetween('applied_at', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ])->count();
        $weeklyPercentage = min(100, (int) round(($weeklyCount / $weeklyTarget) * 100));

        $followUpApplications = JobApplication::query()
            ->whereIn('status', ['applied', 'screening', 'interview'])
            ->where(function ($q) {
                $q->where('last_status_change_at', '<=', now()->subDays(7))
                    ->orWhere(function ($sub) {
                        $sub->whereNull('last_status_change_at')
                            ->where('applied_at', '<=', now()->subDays(7));
                    });
            })
            ->orderBy('last_status_change_at', 'asc')
            ->orderBy('applied_at', 'asc')
            ->get();

        $followUpApplications->each(function ($app) {
            $referenceDate = $app->last_status_change_at ?? $app->applied_at;
            $app->days_waiting = (int) $referenceDate->diffInDays(now());
        });

        $recentApplications = JobApplication::orderBy('applied_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();

        $expiringOffers = OfferDetail::with('jobApplication')
            ->whereNotNull('deadline_at')
            ->whereHas('jobApplication', fn ($q) => $q->where('status', 'offer'))
            ->where('deadline_at', '>=', now()->toDateString())
            ->where('deadline_at', '<=', now()->addDays(3)->toDateString())
            ->orderBy('deadline_at', 'asc')
            ->get();

        $metrics = [
            'total' => $total,
            'active' => $active,
            'interview' => $interview,
            'offer' => $offer,
            'hired' => $hired,
            'rejected' => $rejected,
            'win_rate' => $winRate,
        ];

        return view('dashboard', [
            'metrics' => $metrics,
            'total' => $total,
            'active' => $active,
            'interview' => $interview,
            'wishlist' => $wishlist,
            'applied' => $applied,
            'screening' => $screening,
            'offer' => $offer,
            'hired' => $hired,
            'rejected' => $rejected,
            'win_rate' => $winRate,
            'weeklyTarget' => $weeklyTarget,
            'weeklyCount' => $weeklyCount,
            'weeklyPercentage' => $weeklyPercentage,
            'followUpApplications' => $followUpApplications,
            'expiringOffers' => $expiringOffers,
            'recentApplications' => $recentApplications,
            'statuses' => JobApplicationController::STATUSES,
        ]);
    }

    /**
     * Persist the signed-in user's weekly application target.
     *
     * Previously the target was read from the query string only, so it reset to
     * the default on every navigation. It now lives on the user row.
     */
    public function updateTarget(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'target' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $request->user()->update(['weekly_target' => $validated['target']]);

        return redirect()
            ->route('dashboard')
            ->with('status', 'Target mingguan disimpan.');
    }
}
