<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use App\Models\StatusHistory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    /**
     * The stages in the recruitment funnel in sequential order.
     *
     * @var list<string>
     */
    public const FUNNEL_STAGES = [
        'wishlist',
        'applied',
        'screening',
        'interview',
        'offer',
        'hired',
    ];

    /**
     * Human-readable Indonesian labels for each stage.
     *
     * @var array<string, string>
     */
    public const STAGE_LABELS = [
        'wishlist' => 'Wishlist',
        'applied' => 'Terkirim',
        'screening' => 'Screening',
        'interview' => 'Interview',
        'offer' => 'Offering',
        'hired' => 'Diterima',
        'rejected' => 'Ditolak',
    ];

    /**
     * Display recruitment analytics and insights.
     */
    public function index(Request $request): View
    {
        $totalApplications = JobApplication::count();

        // 1. Stage Funnel Conversion
        $funnel = $this->calculateFunnel($totalApplications);

        // 2. Rejection Analysis (Titik Gugur)
        $rejectionAnalysis = $this->calculateRejectionAnalysis();
        $totalRejected = $rejectionAnalysis['total_rejected'];
        $rejectionBreakdown = $rejectionAnalysis['breakdown'];
        $primaryRejectionStage = $rejectionAnalysis['primary_stage'];
        $actionableAdvice = $rejectionAnalysis['advice'];

        // 3. Time-to-response & Durations
        $timeToResponse = $this->calculateTimeToResponse();
        $avgFirstResponseDays = $timeToResponse['avg_first_response_days'];
        $avgOfferDays = $timeToResponse['avg_offer_days'];

        // 4. Weekly Activity Volume (Past 8 Weeks)
        $weeklyVolume = $this->calculateWeeklyVolume();

        // Key KPI metrics
        $interviewReachedCount = $funnel['interview']['count'] ?? 0;
        $totalOffers = $funnel['offer']['count'] ?? 0;

        return view('analytics.index', [
            // Funnel
            'funnel' => $funnel,
            'stages' => self::FUNNEL_STAGES,
            'stageLabels' => self::STAGE_LABELS,

            // Rejections
            'rejectionAnalysis' => $rejectionAnalysis,
            'totalRejected' => $totalRejected,
            'rejectionBreakdown' => $rejectionBreakdown,
            'primaryRejectionStage' => $primaryRejectionStage,
            'actionableAdvice' => $actionableAdvice,

            // Time to response
            'timeToResponse' => $timeToResponse,
            'avgFirstResponseDays' => $avgFirstResponseDays,
            'avgOfferDays' => $avgOfferDays,

            // Weekly Volume
            'weeklyVolume' => $weeklyVolume,

            // Activity Heatmap (365 days)
            'heatmap' => $this->calculateHeatmap(),

            // KPI Summary
            'totalApplications' => $totalApplications,
            'total' => $totalApplications,
            'interviewReachedCount' => $interviewReachedCount,
            'totalInterviewReached' => $interviewReachedCount,
            'totalOffers' => $totalOffers,
            'totalOffer' => $totalOffers,
            'offerCount' => $totalOffers,
        ]);
    }

    /**
     * Calculate recruitment funnel counts, percentages, and conversion rates.
     *
     * @return array<string, array{stage: string, label: string, count: int, percentage: float, conversion_rate: float, drop_off_rate: float}>
     */
    protected function calculateFunnel(int $totalApplications): array
    {
        $funnel = [];
        $prevCount = null;

        foreach (self::FUNNEL_STAGES as $index => $stage) {
            $count = JobApplication::where(function ($query) use ($stage) {
                $query->where('status', $stage)
                    ->orWhereHas('statusHistories', function ($q) use ($stage) {
                        $q->where('to_status', $stage);
                    });
            })->count();

            $percentage = $totalApplications > 0
                ? round(($count / $totalApplications) * 100, 1)
                : 0.0;

            $conversionRate = 0.0;
            if ($index === 0) {
                $conversionRate = $totalApplications > 0
                    ? round(($count / $totalApplications) * 100, 1)
                    : 0.0;
            } elseif ($prevCount !== null && $prevCount > 0) {
                $conversionRate = round(($count / $prevCount) * 100, 1);
            }

            $dropOffRate = 0.0;
            if ($index > 0 && $prevCount !== null && $prevCount > 0) {
                $dropOffCount = max(0, $prevCount - $count);
                $dropOffRate = round(($dropOffCount / $prevCount) * 100, 1);
            }

            $funnel[$stage] = [
                'stage' => $stage,
                'label' => self::STAGE_LABELS[$stage] ?? ucfirst($stage),
                'count' => $count,
                'percentage' => $percentage,
                'conversion_rate' => $conversionRate,
                'drop_off_rate' => $dropOffRate,
            ];

            $prevCount = $count;
        }

        return $funnel;
    }

    /**
     * Calculate rejection breakdown by previous stage and generate actionable advice.
     *
     * @return array{total_rejected: int, breakdown: array<string, array{stage: string, label: string, count: int, percentage: float}>, primary_stage: ?string, advice: array{title: string, badge: string, badge_color: string, description: string}}
     */
    protected function calculateRejectionAnalysis(): array
    {
        $totalRejected = JobApplication::where('status', 'rejected')->count();

        // Group rejections by from_status in status_histories where to_status = 'rejected'
        $rejectionHistories = StatusHistory::where('to_status', 'rejected')
            ->selectRaw('from_status, COUNT(DISTINCT job_application_id) as count')
            ->groupBy('from_status')
            ->pluck('count', 'from_status')
            ->toArray();

        $trackedStages = ['applied', 'screening', 'interview', 'offer'];
        foreach (array_keys($rejectionHistories) as $stageKey) {
            if ($stageKey !== null && !in_array($stageKey, $trackedStages, true)) {
                $trackedStages[] = $stageKey;
            }
        }

        $historyRejectionCount = array_sum($rejectionHistories);
        $untrackedRejections = max(0, $totalRejected - $historyRejectionCount);

        $breakdown = [];
        foreach ($trackedStages as $stageKey) {
            $count = (int) ($rejectionHistories[$stageKey] ?? 0);
            $percentage = $totalRejected > 0 ? round(($count / $totalRejected) * 100, 1) : 0.0;

            $breakdown[$stageKey] = [
                'stage' => $stageKey,
                'label' => self::STAGE_LABELS[$stageKey] ?? ucfirst($stageKey),
                'count' => $count,
                'percentage' => $percentage,
            ];
        }

        if ($untrackedRejections > 0) {
            $percentage = $totalRejected > 0 ? round(($untrackedRejections / $totalRejected) * 100, 1) : 0.0;
            $breakdown['other'] = [
                'stage' => 'other',
                'label' => 'Lainnya / Langsung',
                'count' => $untrackedRejections,
                'percentage' => $percentage,
            ];
        }

        // Determine primary drop-off stage
        $primaryStage = null;
        $maxCount = 0;
        foreach ($breakdown as $key => $item) {
            if ($item['count'] > $maxCount) {
                $maxCount = $item['count'];
                $primaryStage = $key;
            }
        }

        $adviceMap = [
            'applied' => [
                'title' => 'Tingkatkan Kualitas Berkas & ATS CV',
                'badge' => 'Perhatian: Seleksi Berkas',
                'badge_color' => 'amber',
                'description' => 'Mayoritas penolakan terjadi di tahap awal (Terkirim). Optimalkan format CV agar lolos ATS, sesuaikan kata kunci dengan deskripsi lowongan, dan cantumkan portofolio relevan.',
            ],
            'screening' => [
                'title' => 'Pertajam Profil & Nilai Tambah Pengalaman',
                'badge' => 'Perhatian: Screening Rekruter',
                'badge_color' => 'orange',
                'description' => 'Banyak gugur pada tahap Screening HR/Rekruter. Evaluasi kesesuaian ekspektasi gaji, kualifikasi teknis utama, dan perkuat ringkasan profesional pada resume Anda.',
            ],
            'interview' => [
                'title' => 'Perdalam Latihan Wawancara & Studi Kasus',
                'badge' => 'Perhatian: Wawancara Kerja',
                'badge_color' => 'red',
                'description' => 'Titik gugur terbesar ada pada tahap Interview. Gunakan metode STAR (Situation, Task, Action, Result), perdalam pembahasan proyek nyata, dan siapkan pertanyaan strategis untuk pewawancara.',
            ],
            'offer' => [
                'title' => 'Tinjau Ekspektasi Penawaran & Negosiasi',
                'badge' => 'Perhatian: Tahap Penawaran',
                'badge_color' => 'purple',
                'description' => 'Penolakan terjadi di tahap Offering. Evaluasi apakah ekspektasi kompensasi dan benefit sudah selaras dengan penawaran pasar, serta komunikasikan kebutuhan secara transparan.',
            ],
        ];

        if ($totalRejected === 0) {
            $advice = [
                'title' => 'Performa Lamaran Sangat Baik',
                'badge' => 'Status Optimal',
                'badge_color' => 'green',
                'description' => 'Belum ada catatan lamaran yang ditolak. Terus jaga konsistensi melamar dan perbarui status setiap tahapan tepat waktu.',
            ];
        } else {
            $advice = $adviceMap[$primaryStage] ?? [
                'title' => 'Evaluasi Kualitas Lamaran',
                'badge' => 'Evaluasi Menyeluruh',
                'badge_color' => 'blue',
                'description' => 'Tinjau setiap tahapan yang mengalami penurunan konversi untuk memperbaiki pendekatan lamaran kerja Anda.',
            ];
        }

        return [
            'total_rejected' => $totalRejected,
            'breakdown' => $breakdown,
            'primary_stage' => $primaryStage,
            'advice' => $advice,
        ];
    }

    /**
     * Compute average response durations in days.
     *
     * @return array{avg_first_response_days: float, avg_offer_days: float, first_response_count: int, offer_count: int}
     */
    protected function calculateTimeToResponse(): array
    {
        $applications = JobApplication::with(['statusHistories' => function ($query) {
            $query->orderBy('created_at', 'asc');
        }])->whereNotNull('applied_at')->get();

        $firstResponseDaysList = [];
        $offerDaysList = [];

        foreach ($applications as $app) {
            $appliedDate = Carbon::parse($app->applied_at)->startOfDay();

            // 1. First status change to screening, interview, or rejected
            $firstResponseHistory = $app->statusHistories->first(function ($h) {
                return in_array($h->to_status, ['screening', 'interview', 'rejected'], true);
            });

            if ($firstResponseHistory && $firstResponseHistory->created_at) {
                $changeDate = Carbon::parse($firstResponseHistory->created_at)->startOfDay();
                $firstResponseDaysList[] = max(0, (int) $appliedDate->diffInDays($changeDate, false));
            } elseif (in_array($app->status, ['screening', 'interview', 'rejected'], true)) {
                $changeDate = Carbon::parse($app->last_status_change_at ?? $app->created_at ?? now())->startOfDay();
                $firstResponseDaysList[] = max(0, (int) $appliedDate->diffInDays($changeDate, false));
            }

            // 2. Average days to offer or hired
            $offerHistory = $app->statusHistories->first(function ($h) {
                return in_array($h->to_status, ['offer', 'hired'], true);
            });

            if ($offerHistory && $offerHistory->created_at) {
                $offerDate = Carbon::parse($offerHistory->created_at)->startOfDay();
                $offerDaysList[] = max(0, (int) $appliedDate->diffInDays($offerDate, false));
            } elseif (in_array($app->status, ['offer', 'hired'], true)) {
                $offerDate = Carbon::parse($app->last_status_change_at ?? $app->created_at ?? now())->startOfDay();
                $offerDaysList[] = max(0, (int) $appliedDate->diffInDays($offerDate, false));
            }
        }

        $avgFirstResponseDays = count($firstResponseDaysList) > 0
            ? round(array_sum($firstResponseDaysList) / count($firstResponseDaysList), 1)
            : 0.0;

        $avgOfferDays = count($offerDaysList) > 0
            ? round(array_sum($offerDaysList) / count($offerDaysList), 1)
            : 0.0;

        return [
            'avg_first_response_days' => $avgFirstResponseDays,
            'avg_offer_days' => $avgOfferDays,
            'first_response_count' => count($firstResponseDaysList),
            'offer_count' => count($offerDaysList),
        ];
    }

    /**
     * Compute weekly application volume for the past 8 weeks.
     *
     * @return list<array{start_date: string, end_date: string, label: string, count: int, date_range: string, is_current: bool}>
     */
    protected function calculateWeeklyVolume(): array
    {
        $weeklyVolume = [];
        $currentDate = now();

        for ($i = 7; $i >= 0; $i--) {
            $weekDate = $currentDate->copy()->subWeeks($i);
            $startOfWeek = $weekDate->copy()->startOfWeek();
            $endOfWeek = $weekDate->copy()->endOfWeek();

            $startDateStr = $startOfWeek->toDateString();
            $endDateStr = $endOfWeek->toDateString();

            $weekOfMonth = (int) ceil($startOfWeek->day / 7);
            $monthLabel = $startOfWeek->format('M');
            $label = sprintf('W%d %s', $weekOfMonth, $monthLabel);

            $count = JobApplication::whereBetween('applied_at', [$startDateStr, $endDateStr])->count();

            $weeklyVolume[] = [
                'start_date' => $startDateStr,
                'end_date' => $endDateStr,
                'label' => $label,
                'count' => $count,
                'date_range' => $startOfWeek->format('d M') . ' - ' . $endOfWeek->format('d M'),
                'is_current' => $i === 0,
            ];
        }

        return $weeklyVolume;
    }

    /**
     * Build a 53-week activity heatmap for the past 365 days.
     *
     * @return array{counts: array<string, int>, max: int, weeks: list<list<array{date: string, count: int, level: int, is_future: bool}|null>>}
     */
    protected function calculateHeatmap(): array
    {
        $today = Carbon::today();
        $start = $today->copy()->subDays(364);

        // Pull all applied_at dates within the 365-day window
        $rows = JobApplication::selectRaw('DATE(applied_at) as day, COUNT(*) as cnt')
            ->whereDate('applied_at', '>=', $start)
            ->whereDate('applied_at', '<=', $today)
            ->groupByRaw('DATE(applied_at)')
            ->pluck('cnt', 'day')
            ->toArray();

        // Cast keys to strings (already strings from DB, but be safe)
        $counts = [];
        foreach ($rows as $day => $cnt) {
            $counts[(string) $day] = (int) $cnt;
        }

        $max = $counts ? max($counts) : 0;

        // Build 53 weeks × 7 days (Sun→Sat), starting from the Sunday
        // that is 52 full weeks ago (so week 53 ends this Saturday or later).
        // We anchor on the Sunday on or before $start.
        $gridStart = $start->copy()->startOfWeek(Carbon::SUNDAY);

        $weeks = [];
        $cursor = $gridStart->copy();
        for ($w = 0; $w < 53; $w++) {
            $week = [];
            for ($d = 0; $d < 7; $d++) {
                $dateStr = $cursor->format('Y-m-d');
                $isFuture = $cursor->gt($today);
                $isPast365 = $cursor->lt($start);

                if ($isPast365) {
                    // Days before our 365-day window → null (empty padding cell)
                    $week[] = null;
                } else {
                    $count = $counts[$dateStr] ?? 0;

                    if ($isFuture) {
                        $level = 0;
                    } elseif ($count === 0) {
                        $level = 0;
                    } elseif ($count === 1) {
                        $level = 1;
                    } elseif ($count <= 3) {
                        $level = 2;
                    } elseif ($count <= 6) {
                        $level = 3;
                    } else {
                        $level = 4;
                    }

                    $week[] = [
                        'date'      => $dateStr,
                        'count'     => $count,
                        'level'     => $level,
                        'is_future' => $isFuture,
                    ];
                }
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        return [
            'counts' => $counts,
            'max'    => $max,
            'weeks'  => $weeks,
        ];
    }
}
