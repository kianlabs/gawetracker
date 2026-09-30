@extends('layouts.app')

@section('title', 'Analisis Pipeline Rekrutmen')

@section('styles')
<style>
    /* Funnel fill colors per stage */
    .fill-wishlist  { background-color: hsl(240 3.8% 46.1%); }
    .fill-applied   { background-color: hsl(217 91% 50%); }
    .fill-screening { background-color: hsl(38 92% 50%); }
    .fill-interview { background-color: hsl(262 83% 58%); }
    .fill-offer     { background-color: hsl(142 76% 40%); }
    .fill-hired     { background-color: hsl(142 76% 30%); }

    /* Bar chart — horizontal bars for status distribution */
    .hbar-track {
        height: 8px;
        background-color: hsl(var(--muted));
        border-radius: 9999px;
        overflow: hidden;
        flex: 1;
    }
    .hbar-fill {
        height: 100%;
        border-radius: 9999px;
        background-color: hsl(var(--foreground));
        transition: width 0.3s ease;
    }
    .hbar-fill.fill-wishlist  { background-color: hsl(240 3.8% 46.1%); }
    .hbar-fill.fill-applied   { background-color: hsl(217 91% 50%); }
    .hbar-fill.fill-screening { background-color: hsl(38 92% 50%); }
    .hbar-fill.fill-interview { background-color: hsl(262 83% 58%); }
    .hbar-fill.fill-offer     { background-color: hsl(142 76% 40%); }
    .hbar-fill.fill-hired     { background-color: hsl(142 76% 30%); }
    .hbar-fill.fill-rejected  { background-color: hsl(0 84.2% 50%); }

    /* Conversion rate label coloring */
    .conv-high  { color: hsl(142 76% 30%); font-weight: 600; }
    .conv-mid   { color: hsl(38 92% 40%); font-weight: 600; }
    .conv-low   { color: hsl(0 84.2% 45%); font-weight: 600; }
</style>
@endsection

@section('content')
<div class="space-y-6">

    {{-- PAGE HEADER --}}
    <div class="page-header">
        <div class="page-header-meta">
            <h1 class="page-title">Analisis Pipeline Rekrutmen</h1>
            <p class="page-subtitle">Di tahap mana lamaran Anda gugur? Seberapa konsisten volume mingguan?</p>
        </div>
    </div>

    {{-- SECTION 1: KPI STRIP — 4 metric cards --}}
    @php
        $offerCount  = $funnel['offer']['count']  ?? 0;
        $hiredCount  = $funnel['hired']['count']  ?? 0;
        $interviewCt = $funnel['interview']['count'] ?? 0;
        $appliedCt   = $funnel['applied']['count'] ?? 0;

        // Win Rate: (offer + hired) / interview, only when interview > 0
        $winRate = ($interviewCt > 0)
            ? round((($offerCount + $hiredCount) / $interviewCt) * 100, 1)
            : null;

        // Rejection rate: rejected / total
        $rejRate = ($totalApplications > 0)
            ? round(($totalRejected / $totalApplications) * 100, 1)
            : null;

        // Active: all statuses except hired and rejected
        $activeCount = 0;
        foreach (['wishlist','applied','screening','interview','offer'] as $s) {
            $activeCount += $funnel[$s]['count'] ?? 0;
        }
    @endphp
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="metric-card accent-primary">
            <div class="metric-label">Total Lamaran</div>
            <div class="metric-value">{{ $totalApplications }}</div>
            <div class="metric-footer">Semua berkas tercatat</div>
        </div>
        <div class="metric-card accent-green">
            <div class="metric-label">Win Rate Interview</div>
            <div class="metric-value {{ $winRate !== null ? '' : 'small' }}">
                @if($winRate !== null)
                    {{ $winRate }}<span style="font-size:1rem;font-weight:500">%</span>
                @else
                    <span class="text-muted" style="font-size:1rem">Belum ada data</span>
                @endif
            </div>
            <div class="metric-footer">Offer + Diterima dari tahap Interview</div>
        </div>
        <div class="metric-card accent-amber">
            <div class="metric-label">Tingkat Penolakan</div>
            <div class="metric-value {{ $rejRate !== null ? '' : 'small' }}">
                @if($rejRate !== null)
                    {{ $rejRate }}<span style="font-size:1rem;font-weight:500">%</span>
                @else
                    <span class="text-muted" style="font-size:1rem">Belum ada data</span>
                @endif
            </div>
            <div class="metric-footer">Dari total lamaran yang dikirim</div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Aktif Diproses</div>
            <div class="metric-value">{{ $activeCount }}</div>
            <div class="metric-footer">Dalam pipeline saat ini</div>
        </div>
    </div>

    {{-- SECTION 2: FUNNEL — primary question --}}
    <div class="section-card">
        <div class="section-header">
            <span class="section-title">Di mana lamaran Anda gugur?</span>
            <span class="text-xs text-muted tabular-nums">{{ $totalApplications }} total &bull; {{ $totalRejected }} ditolak</span>
        </div>
        <div class="section-body" style="padding: 1rem;">
            @php
                $stageKeys = ['wishlist', 'applied', 'screening', 'interview', 'offer', 'hired'];
                $stageBadgeMap = [
                    'wishlist'  => 'badge-secondary',
                    'applied'   => 'badge-info',
                    'screening' => 'badge-warning',
                    'interview' => 'badge-warning',
                    'offer'     => 'badge-purple',
                    'hired'     => 'badge-success',
                ];
            @endphp

            @if($totalApplications === 0)
                <div class="empty-state">
                    <div class="empty-state-icon">&#9638;</div>
                    <div class="empty-state-title">Belum ada data lamaran</div>
                    <div class="empty-state-desc">Tambahkan lamaran pertama Anda agar funnel dapat dihitung.</div>
                    <a href="{{ route('applications.create') }}" class="btn btn-default btn-sm" style="margin-top:1rem">Tambah Lamaran</a>
                </div>
            @else
                <div class="space-y-2">
                    @foreach($stageKeys as $idx => $key)
                        @php
                            $item = $funnel[$key] ?? [
                                'stage'           => $key,
                                'label'           => ucfirst($key),
                                'count'           => 0,
                                'percentage'      => 0.0,
                                'conversion_rate' => 0.0,
                                'drop_off_rate'   => 0.0,
                            ];
                            $pct      = (float) $item['percentage'];
                            $barWidth = max(2, min(100, $pct));
                            $badge    = $stageBadgeMap[$key] ?? 'badge-secondary';

                            // Conversion rate color
                            $convRate = (float) ($item['conversion_rate'] ?? 0);
                            $convClass = $convRate >= 60 ? 'conv-high' : ($convRate >= 30 ? 'conv-mid' : 'conv-low');

                            // Rejection count at this stage from rejectionBreakdown
                            $rejAtStage = $rejectionBreakdown[$key]['count'] ?? 0;
                        @endphp

                        {{-- Conversion connector between stages --}}
                        @if($idx > 0)
                            @php $prevKey = $stageKeys[$idx - 1]; $prevCount = $funnel[$prevKey]['count'] ?? 0; @endphp
                            <div class="flex items-center gap-2" style="padding: 0.125rem 1rem;">
                                <span class="text-muted" style="font-size:0.7rem; line-height:1">&#9660;</span>
                                @if($prevCount > 0)
                                    <span class="text-xs {{ $convClass }}">Konversi {{ $item['conversion_rate'] }}%</span>
                                    @if($rejAtStage > 0)
                                        <span class="text-xs text-muted">&bull;</span>
                                        <span class="text-xs" style="color:hsl(0 84.2% 45%)">{{ $rejAtStage }} gugur di sini</span>
                                    @endif
                                @else
                                    <span class="text-xs text-muted">Belum ada data konversi</span>
                                @endif
                            </div>
                        @endif

                        <div class="funnel-step">
                            <div class="flex items-center justify-between gap-3 mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="badge {{ $badge }}" style="min-width:6.5rem;text-align:center">{{ $item['label'] }}</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-xs text-muted">{{ $pct }}% dari total</span>
                                    <span class="font-bold tabular-nums" style="font-size:1.125rem">{{ $item['count'] }}</span>
                                    <span class="text-xs text-muted">lamaran</span>
                                </div>
                            </div>
                            <div class="funnel-bar-track">
                                <div class="funnel-bar-fill fill-{{ $key }}" style="width: {{ $barWidth }}%;"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- SECTION 3: SPLIT — Distribusi + Ringkasan --}}
    <div class="split-grid">

        {{-- LEFT: Distribusi Status (horizontal bar chart, CSS widths only) --}}
        <div class="section-card">
            <div class="section-header">
                <span class="section-title">Distribusi Status Lamaran</span>
            </div>
            <div class="section-body" style="padding: 1rem;">
                @php
                    $allStageKeys = ['wishlist','applied','screening','interview','offer','hired'];
                    $hasAny = $totalApplications > 0;
                @endphp
                @if(!$hasAny)
                    <p class="text-sm text-muted">Belum ada lamaran untuk ditampilkan.</p>
                @else
                    <div class="space-y-3">
                        @foreach($allStageKeys as $key)
                            @php
                                $item     = $funnel[$key] ?? ['label' => ucfirst($key), 'count' => 0, 'percentage' => 0.0];
                                $barWidth = max(0, min(100, (float)$item['percentage']));
                            @endphp
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-sm font-semibold">{{ $item['label'] }}</span>
                                    <span class="text-xs tabular-nums text-muted">{{ $item['count'] }} &bull; {{ $item['percentage'] }}%</span>
                                </div>
                                <div class="hbar-track">
                                    <div class="hbar-fill fill-{{ $key }}" style="width: {{ $barWidth }}%;"></div>
                                </div>
                            </div>
                        @endforeach

                        {{-- Rejected row --}}
                        @php
                            $rejPct = ($totalApplications > 0) ? round(($totalRejected / $totalApplications) * 100, 1) : 0;
                            $rejBarWidth = max(0, min(100, $rejPct));
                        @endphp
                        @if($totalRejected > 0)
                            <div class="border-t" style="padding-top:0.75rem">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-sm font-semibold">Ditolak</span>
                                    <span class="text-xs tabular-nums text-muted">{{ $totalRejected }} &bull; {{ $rejPct }}%</span>
                                </div>
                                <div class="hbar-track">
                                    <div class="hbar-fill fill-rejected" style="width: {{ $rejBarWidth }}%;"></div>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- RIGHT: Ringkasan Analisis (text-based insights) --}}
        <div class="section-card">
            <div class="section-header">
                <span class="section-title">Ringkasan Analisis</span>
            </div>
            <div class="section-body" style="padding: 1rem;">
                @php
                    // Find stage with highest drop-off (most rejections)
                    $worstStage = null;
                    $worstCount = 0;
                    foreach ($rejectionBreakdown as $rKey => $rItem) {
                        if (($rItem['count'] ?? 0) > $worstCount) {
                            $worstCount = $rItem['count'];
                            $worstStage = $rItem;
                        }
                    }
                @endphp

                <div class="space-y-4">

                    {{-- Worst stage --}}
                    <div>
                        <div class="text-xs font-semibold text-muted uppercase" style="letter-spacing:0.04em;margin-bottom:0.375rem">Tahap paling banyak gugur</div>
                        @if($worstStage && $worstCount > 0)
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-semibold text-sm">{{ $worstStage['label'] }}</span>
                                <span class="badge badge-destructive">{{ $worstCount }} ditolak</span>
                            </div>
                            @if($primaryRejectionStage)
                                <p class="text-xs text-muted" style="margin-top:0.25rem">{{ $actionableAdvice['description'] ?? '' }}</p>
                            @endif
                        @else
                            <p class="text-sm text-muted">Belum ada penolakan tercatat.</p>
                        @endif
                    </div>

                    <div class="separator"></div>

                    {{-- Waktu rata-rata --}}
                    <div>
                        <div class="text-xs font-semibold text-muted uppercase" style="letter-spacing:0.04em;margin-bottom:0.375rem">Waktu rata-rata di setiap tahap</div>
                        <div class="space-y-1">
                            @if($timeToResponse['first_response_count'] > 0)
                                <div class="flex items-center justify-between">
                                    <span class="text-sm">Respons pertama rekruter</span>
                                    <span class="font-semibold tabular-nums text-sm">{{ $avgFirstResponseDays }} hari</span>
                                </div>
                            @endif
                            @if($timeToResponse['offer_count'] > 0)
                                <div class="flex items-center justify-between">
                                    <span class="text-sm">Hingga penawaran kerja</span>
                                    <span class="font-semibold tabular-nums text-sm">{{ $avgOfferDays }} hari</span>
                                </div>
                            @endif
                            @if($timeToResponse['first_response_count'] === 0 && $timeToResponse['offer_count'] === 0)
                                <p class="text-sm text-muted">Data waktu belum tersedia. Perbarui status lamaran secara berkala agar waktu transisi dapat dihitung.</p>
                            @endif
                        </div>
                    </div>

                    <div class="separator"></div>

                    {{-- Win rate summary --}}
                    <div>
                        <div class="text-xs font-semibold text-muted uppercase" style="letter-spacing:0.04em;margin-bottom:0.375rem">Efektivitas tahap interview</div>
                        @if($interviewCt > 0)
                            <div class="flex items-center justify-between">
                                <span class="text-sm">Mencapai interview</span>
                                <span class="font-semibold tabular-nums text-sm">{{ $interviewCt }}</span>
                            </div>
                            <div class="flex items-center justify-between" style="margin-top:0.25rem">
                                <span class="text-sm">Konversi ke offer/diterima</span>
                                <span class="font-semibold tabular-nums text-sm">{{ $winRate !== null ? $winRate.'%' : '-' }}</span>
                            </div>
                        @else
                            <p class="text-sm text-muted">Belum ada lamaran yang mencapai tahap interview.</p>
                        @endif
                    </div>

                </div>
            </div>
        </div>

    </div>{{-- end split-grid --}}

    {{-- SECTION 4: ACTIVITY HEATMAP --}}
    <div class="section-card">
        <div class="section-header">
            <span class="section-title">Aktivitas Lamaran 12 Bulan Terakhir</span>
            <span class="text-xs text-muted">{{ $heatmap['max'] > 0 ? 'Maks '.$heatmap['max'].' lamaran/hari' : 'Belum ada aktivitas' }}</span>
        </div>
        <div class="section-body" style="padding: 1rem;">
            @php
                // Build month labels: for each week, determine if its first non-null day
                // starts a new month; store month abbreviation at that week index.
                $monthLabels = [];
                $monthNames  = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
                $lastMonth   = null;
                foreach ($heatmap['weeks'] as $wi => $week) {
                    $label = '';
                    foreach ($week as $cell) {
                        if ($cell !== null) {
                            $m = (int) substr($cell['date'], 5, 2);
                            if ($m !== $lastMonth) {
                                $label     = $monthNames[$m - 1];
                                $lastMonth = $m;
                            }
                            break;
                        }
                    }
                    $monthLabels[$wi] = $label;
                }
            @endphp

            <div style="display:flex; gap:0; align-items:flex-start;">
                {{-- Day-of-week labels --}}
                <div class="heatmap-day-labels" style="padding-top:22px;">
                    <div class="heatmap-day-label">Sen</div>
                    <div class="heatmap-day-label"></div>
                    <div class="heatmap-day-label">Rab</div>
                    <div class="heatmap-day-label"></div>
                    <div class="heatmap-day-label">Jum</div>
                    <div class="heatmap-day-label"></div>
                    <div class="heatmap-day-label"></div>
                </div>

                <div style="flex:1; min-width:0;">
                    {{-- Month labels row --}}
                    <div class="heatmap-labels-month">
                        @foreach($heatmap['weeks'] as $wi => $week)
                            <div class="heatmap-month-label">{{ $monthLabels[$wi] }}</div>
                        @endforeach
                    </div>

                    {{-- Heatmap grid --}}
                    <div class="heatmap-grid">
                        @foreach($heatmap['weeks'] as $week)
                            <div class="heatmap-week">
                                @foreach($week as $cell)
                                    @if($cell === null)
                                        <div class="heatmap-cell" style="background:transparent;"></div>
                                    @else
                                        <div
                                            class="heatmap-cell level-{{ $cell['level'] }}{{ $cell['is_future'] ? ' is-future' : '' }}"
                                            title="{{ $cell['date'] }}: {{ $cell['count'] }} lamaran"
                                        ></div>
                                    @endif
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Legend --}}
            <div style="display:flex; align-items:center; gap:6px; margin-top:0.75rem; font-size:0.65rem; color:hsl(var(--muted-foreground));">
                <span>Kurang</span>
                <div class="heatmap-cell level-0"></div>
                <div class="heatmap-cell level-1"></div>
                <div class="heatmap-cell level-2"></div>
                <div class="heatmap-cell level-3"></div>
                <div class="heatmap-cell level-4"></div>
                <span>Lebih</span>
            </div>
        </div>
    </div>

</div>
@endsection
