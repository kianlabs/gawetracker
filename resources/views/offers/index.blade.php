@extends('layouts.app')

@section('title', 'Matriks Komparasi Offer')

@section('styles')
<style>
    .table-wrapper {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .table th.sticky-col,
    .table td.sticky-col {
        position: sticky;
        left: 0;
        background-color: hsl(var(--card));
        z-index: 10;
        border-right: 1px solid hsl(var(--border));
    }
    .table thead th.sticky-col {
        background-color: hsl(var(--muted));
        z-index: 20;
    }
    .table tbody tr:hover td.sticky-col {
        background-color: hsl(var(--muted));
    }
    .company-link {
        font-weight: 600;
        font-size: 0.875rem;
        color: hsl(var(--foreground));
        text-decoration: none;
    }
    .company-link:hover {
        text-decoration: underline;
    }
    .insight-row {
        display: flex;
        flex-wrap: wrap;
        gap: 1.5rem;
        padding: 0.75rem 1rem;
        border-top: 1px solid hsl(var(--border));
        background-color: hsl(var(--muted));
        font-size: 0.75rem;
        color: hsl(var(--muted-foreground));
    }
    .insight-item strong {
        color: hsl(var(--foreground));
        font-weight: 600;
    }
</style>
@endsection

@section('content')
<div class="space-y-6">

    {{-- PAGE HEADER --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Matriks Komparasi Offer</h1>
            <p class="page-subtitle">Bandingkan kompensasi, skema kerja, dan tenggat setiap tawaran untuk keputusan karier terbaik.</p>
        </div>
        <div class="page-actions">
            @if ($applications->isNotEmpty())
                <span class="badge badge-success">{{ $applications->count() }} tawaran aktif</span>
            @endif
        </div>
    </div>

    @if ($applications->isEmpty())
        {{-- EMPTY STATE --}}
        <div class="empty-state">
            <div class="empty-state-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>
                </svg>
            </div>
            <p class="empty-state-title">Belum Ada Penawaran Kerja Tercatat</p>
            <p class="empty-state-desc">Terus lamar! Ketika status lamaran berubah menjadi Offering, lamaran akan muncul di sini secara otomatis.</p>
            <a href="{{ route('applications.index') }}" class="btn btn-default btn-sm">Lihat Daftar Lamaran</a>
        </div>

    @else
        @php
            // Insight: soonest upcoming deadline
            $soonestApp = $applications
                ->filter(fn($a) => $a->offerDetail?->deadline_at && !$a->offerDetail->deadline_at->isPast())
                ->sortBy(fn($a) => $a->offerDetail->deadline_at)
                ->first();

            // Insight: highest base salary (numeric)
            $highestSalaryApp = $applications
                ->filter(fn($a) => $a->offerDetail?->base_salary !== null)
                ->sortByDesc(fn($a) => $a->offerDetail->base_salary)
                ->first();
        @endphp

        {{-- COMPARISON TABLE --}}
        <div class="section-card">
            <div class="section-header">
                <span class="section-title">Tabel Komparasi</span>
            </div>
            <div class="section-body" style="padding: 0;">
                <div class="table-wrapper table-wrapper--actions">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="sticky-col" style="min-width: 220px;">PERUSAHAAN / POSISI</th>
                                <th style="min-width: 190px;">GAJI / KOMPENSASI</th>
                                <th style="min-width: 180px;">TUNJANGAN</th>
                                <th style="min-width: 130px;">TIPE KERJA</th>
                                <th style="min-width: 150px;">LOKASI</th>
                                <th style="min-width: 170px;">TENGGAT</th>
                                <th style="min-width: 110px;">STATUS</th>
                                <th style="min-width: 90px;">DETAIL</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($applications as $app)
                                @php
                                    $offer = $app->offerDetail;

                                    // Work type badge
                                    $wt = strtolower($app->work_type ?? '');
                                    $wtBadge = match(true) {
                                        str_contains($wt, 'remote')  => 'badge-info',
                                        str_contains($wt, 'hybrid')  => 'badge-purple',
                                        str_contains($wt, 'onsite') || str_contains($wt, 'on-site') || str_contains($wt, 'wfo') => 'badge-outline',
                                        default => 'badge-secondary',
                                    };

                                    // Deadline urgency
                                    $deadlineUrgency = null;
                                    if ($offer?->deadline_at) {
                                        $dl = $offer->deadline_at;
                                        $daysLeft = now()->diffInDays($dl, false);
                                        if ($daysLeft < 0) {
                                            $deadlineUrgency = 'past';
                                        } elseif ($daysLeft < 3) {
                                            $deadlineUrgency = 'critical';
                                        } elseif ($daysLeft < 7) {
                                            $deadlineUrgency = 'warning';
                                        } else {
                                            $deadlineUrgency = 'ok';
                                        }
                                    }
                                @endphp
                                <tr>
                                    {{-- PERUSAHAAN / POSISI (sticky) --}}
                                    <td class="sticky-col">
                                        <div class="flex items-center gap-2">
                                            <span class="co-badge">{{ mb_strtoupper(mb_substr($app->company, 0, 1)) }}</span>
                                            <div>
                                                <a href="{{ route('applications.show', $app) }}" class="company-link">{{ $app->company }}</a>
                                                <div class="text-xs text-muted">{{ $app->position }}</div>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- GAJI / KOMPENSASI --}}
                                    <td>
                                        @if ($offer && $offer->base_salary !== null)
                                            <span class="font-semibold text-sm tabular-nums">Rp {{ number_format($offer->base_salary, 0, ',', '.') }}</span>
                                            <div class="text-xs text-muted">
                                                / {{ match($offer->salary_period) {
                                                    'monthly' => 'bulan',
                                                    'yearly'  => 'tahun',
                                                    'weekly'  => 'minggu',
                                                    'hourly'  => 'jam',
                                                    default   => $offer->salary_period ?: 'bulan',
                                                } }}
                                            </div>
                                        @elseif ($app->salary_note)
                                            <span class="text-sm">{{ $app->salary_note }}</span>
                                        @else
                                            <span class="text-muted">&mdash;</span>
                                        @endif
                                    </td>

                                    {{-- TUNJANGAN --}}
                                    <td>
                                        @if ($offer && $offer->allowance)
                                            <span class="text-xs">{{ $offer->allowance }}</span>
                                        @else
                                            <span class="text-muted">&mdash;</span>
                                        @endif
                                    </td>

                                    {{-- TIPE KERJA --}}
                                    <td>
                                        @if ($app->work_type)
                                            <span class="badge {{ $wtBadge }}">{{ ucfirst($app->work_type) }}</span>
                                        @else
                                            <span class="text-muted">&mdash;</span>
                                        @endif
                                    </td>

                                    {{-- LOKASI --}}
                                    <td>
                                        @if ($app->location)
                                            <span class="text-sm">{{ $app->location }}</span>
                                        @else
                                            <span class="text-muted">&mdash;</span>
                                        @endif
                                    </td>

                                    {{-- TENGGAT --}}
                                    <td>
                                        @if ($offer && $offer->deadline_at)
                                            @if ($deadlineUrgency === 'past')
                                                <span class="badge badge-destructive">Lewat tenggat</span>
                                                <div class="text-xs text-muted mt-1">{{ $offer->deadline_at->format('d M Y') }}</div>
                                            @elseif ($deadlineUrgency === 'critical')
                                                <span class="badge badge-destructive">{{ $offer->deadline_at->format('d M Y') }}</span>
                                                <div class="text-xs text-muted mt-1">{{ $offer->deadline_at->diffForHumans() }}</div>
                                            @elseif ($deadlineUrgency === 'warning')
                                                <span class="badge badge-warning">{{ $offer->deadline_at->format('d M Y') }}</span>
                                                <div class="text-xs text-muted mt-1">{{ $offer->deadline_at->diffForHumans() }}</div>
                                            @else
                                                <span class="text-sm">{{ $offer->deadline_at->format('d M Y') }}</span>
                                                <div class="text-xs text-muted mt-1">{{ $offer->deadline_at->diffForHumans() }}</div>
                                            @endif
                                        @else
                                            <span class="text-muted">&mdash;</span>
                                        @endif
                                    </td>

                                    {{-- STATUS --}}
                                    <td>
                                        @if ($app->status === 'offer')
                                            <span class="badge badge-purple">Offering</span>
                                        @elseif ($app->status === 'hired')
                                            <span class="badge badge-success">Diterima</span>
                                        @else
                                            <span class="badge badge-secondary">{{ ucfirst($app->status) }}</span>
                                        @endif
                                    </td>

                                    {{-- DETAIL --}}
                                    <td>
                                        <a href="{{ route('applications.show', $app) }}" class="btn btn-outline btn-sm">Detail</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- INSIGHT ROW --}}
                @if ($soonestApp || $highestSalaryApp)
                    <div class="insight-row">
                        @if ($soonestApp)
                            <span class="insight-item">
                                Offer paling dekat tenggat:
                                <strong>{{ $soonestApp->company }} — {{ $soonestApp->offerDetail->deadline_at->format('d M Y') }}</strong>
                            </span>
                        @endif
                        @if ($highestSalaryApp)
                            <span class="insight-item">
                                Offer tertinggi estimasi gaji:
                                <strong>{{ $highestSalaryApp->company }} — Rp {{ number_format($highestSalaryApp->offerDetail->base_salary, 0, ',', '.') }}</strong>
                            </span>
                        @endif
                    </div>
                @endif
            </div>
        </div>

    @endif

</div>
@endsection
