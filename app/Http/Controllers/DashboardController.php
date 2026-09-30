<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use App\Models\OfferDetail;
use Illuminate\Http\Request;
use Illuminate\View\View;
class DashboardController extends Controller
{
    /**
     * Display the dashboard view with metrics and follow-up reminders.
     */
    public function index(Request $request): View
    {
        $total = JobApplication::count();

        $active = JobApplication::whereIn('status', [
            'wishlist',
            'applied',
            'screening',
            'interview',
        ])->count();
        $wishlist = JobApplication::where('status', 'wishlist')->count();
        $applied = JobApplication::where('status', 'applied')->count();
        $screening = JobApplication::where('status', 'screening')->count();
        $interview = JobApplication::where('status', 'interview')->count();
        $offer = JobApplication::where('status', 'offer')->count();
        $hired = JobApplication::where('status', 'hired')->count();
        $rejected = JobApplication::where('status', 'rejected')->count();

        $interviewReachedCount = JobApplication::where(function ($query) {
            $query->whereIn('status', ['interview', 'offer', 'hired'])
                ->orWhereHas('statusHistories', function ($q) {
                    $q->whereIn('to_status', ['interview', 'offer', 'hired']);
                });
        })->count();

        $winRate = round(($interviewReachedCount / max(1, $total)) * 100, 1);

        $rawTarget = (int) $request->input('target', 8);
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
            ->whereHas('jobApplication', fn($q) => $q->where('status', 'offer'))
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
}
