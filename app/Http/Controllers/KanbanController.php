<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KanbanController extends Controller
{
    /**
     * Kanban columns configuration mapped to statuses.
     *
     * @var array<string, array{title: string, color: string, badge_bg: string, badge_text: string}>
     */
    public const COLUMNS = [
        'wishlist' => [
            'title' => 'Wishlist',
            'color' => '#64748b',
            'badge_bg' => '#f1f5f9',
            'badge_text' => '#475569',
        ],
        'applied' => [
            'title' => 'Terkirim (Applied)',
            'color' => '#2563eb',
            'badge_bg' => '#dbeafe',
            'badge_text' => '#1d4ed8',
        ],
        'screening' => [
            'title' => 'Screening',
            'color' => '#d97706',
            'badge_bg' => '#fef3c7',
            'badge_text' => '#92400e',
        ],
        'interview' => [
            'title' => 'Interview',
            'color' => '#7c3aed',
            'badge_bg' => '#ede9fe',
            'badge_text' => '#6d28d9',
        ],
        'offer' => [
            'title' => 'Offering',
            'color' => '#059669',
            'badge_bg' => '#d1fae5',
            'badge_text' => '#065f46',
        ],
        'hired' => [
            'title' => 'Diterima (Hired)',
            'color' => '#16a34a',
            'badge_bg' => '#dcfce7',
            'badge_text' => '#166534',
        ],
        'rejected' => [
            'title' => 'Ditolak (Rejected)',
            'color' => '#dc2626',
            'badge_bg' => '#fee2e2',
            'badge_text' => '#991b1b',
        ],
    ];

    /**
     * Display the Kanban board view for job applications.
     */
    public function index(Request $request): View
    {
        $query = JobApplication::with('statusHistories');

        $search = trim((string) $request->input('search'));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('company', 'like', "%{$search}%")
                    ->orWhere('position', 'like', "%{$search}%");
            });
        }

        $workType = $request->input('work_type');
        if ($workType && array_key_exists($workType, JobApplicationController::WORK_TYPES)) {
            $query->where('work_type', $workType);
        }

        $allApplications = $query->orderBy('updated_at', 'desc')->get();

        // Calculate days waiting for each application
        $allApplications->each(function (JobApplication $app) {
            $referenceDate = $app->last_status_change_at ?? $app->applied_at ?? $app->created_at;
            $app->days_waiting = $referenceDate ? (int) $referenceDate->diffInDays(now()) : 0;
        });

        $columns = self::COLUMNS;
        $grouped = $allApplications->groupBy('status');
        $applications = [];
        $counts = [];

        foreach (array_keys($columns) as $statusKey) {
            $items = $grouped->get($statusKey, collect());
            $applications[$statusKey] = $items;
            $counts[$statusKey] = $items->count();
        }

        return view('applications.kanban', [
            'columns' => $columns,
            'counts' => $counts,
            'applications' => collect($applications),
            'allApplications' => $allApplications,
            'search' => $search,
            'workType' => $workType,
            'workTypes' => JobApplicationController::WORK_TYPES,
            'totalCount' => $allApplications->count(),
        ]);
    }
}
