@extends('layouts.app')

@section('title', 'Kanban Board Lamaran')

@section('content')
@php
    $badgeMap = [
        'wishlist'   => 'badge-secondary',
        'applied'    => 'badge-info',
        'screening'  => 'badge-warning',
        'interview'  => 'badge-warning',
        'offer'      => 'badge-purple',
        'hired'      => 'badge-success',
        'rejected'   => 'badge-destructive',
    ];

    /* Column-header accent: 2px top border using semantic colors.
       Reuses the same status palette as badges — no extra color tokens. */
    $topBorderMap = [
        'wishlist'   => 'hsl(240 3.8% 46.1%)',
        'applied'    => 'hsl(217 91% 50%)',
        'screening'  => 'hsl(38 92% 50%)',
        'interview'  => 'hsl(262 83% 55%)',
        'offer'      => 'hsl(142 76% 36%)',
        'hired'      => 'hsl(142 76% 28%)',
        'rejected'   => 'hsl(0 84.2% 50%)',
    ];
@endphp

{{-- ── Page header ──────────────────────────────────────────────────────── --}}
<div class="page-header">
    <div class="page-header-meta">
        <h1 class="page-title">Kanban Board Lamaran</h1>
        <p class="page-subtitle">Satu pandang ke seluruh pipeline — di mana setiap lamaran berdiri sekarang</p>
    </div>
    <div class="page-actions">
        <a href="{{ route('applications.index') }}"  class="btn btn-outline">Tampilan Tabel</a>
        <a href="{{ route('applications.create') }}" class="btn btn-default">+ Tambah Lamaran</a>
    </div>
</div>

{{-- ── Filter bar ───────────────────────────────────────────────────────── --}}
<div class="section-card mb-4">
    <form method="GET" action="{{ route('applications.kanban') }}" class="filter-bar">
        <div style="flex:1 1 220px;">
            <label for="search" class="sr-only">Cari perusahaan atau posisi</label>
            <input type="text"
                   id="search"
                   name="search"
                   class="input"
                   placeholder="Cari perusahaan atau posisi…"
                   value="{{ $search }}">
        </div>
        <div style="flex:0 1 160px;">
            <label for="work_type" class="sr-only">Tipe kerja</label>
            <select id="work_type" name="work_type" class="select">
                <option value="">Semua Tipe</option>
                @foreach ($workTypes as $key => $label)
                    <option value="{{ $key }}" {{ $workType === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-default btn-sm">Filter</button>
        @php
            $activeFilterCount = collect(['search' => $search, 'work_type' => $workType])
                ->filter(fn($val) => $val !== '' && $val !== null)
                ->count();
        @endphp
        @if ($activeFilterCount > 0)
            <a href="{{ route('applications.kanban') }}" class="btn btn-outline btn-sm">
                Reset
                <span class="badge badge-secondary" style="margin-left:0.375rem;">{{ $activeFilterCount }}</span>
            </a>
        @endif
    </form>
</div>

{{-- ── Kanban board ─────────────────────────────────────────────────────── --}}
<div class="kanban-board-container" style="-webkit-overflow-scrolling:touch;">
    @foreach ($columns as $statusKey => $colMeta)
        @php
            $columnApps  = $applications[$statusKey] ?? collect();
            $columnCount = $counts[$statusKey]        ?? $columnApps->count();
            $topColor    = $topBorderMap[$statusKey]  ?? 'hsl(var(--border))';
        @endphp

        <div class="kanban-column" data-status="{{ $statusKey }}">

            {{-- Column header: 2px top accent encodes stage semantics --}}
            <div class="kanban-column-header" style="border-top:2px solid {{ $topColor }};">
                <div class="flex items-center gap-2 min-w-0">
                    <h2 class="text-sm font-semibold truncate">{{ $colMeta['title'] }}</h2>
                </div>
                <span class="badge badge-secondary">{{ $columnCount }}</span>
            </div>

            <div class="kanban-column-content">
                @if ($columnApps->isEmpty())
                    <div style="padding:1.5rem 0.75rem;text-align:center;border:1px dashed hsl(var(--border));border-radius:var(--radius);background-color:hsl(var(--card));">
                        <p class="text-xs text-muted">Tidak ada lamaran di tahap ini</p>
                    </div>
                @else
                    @foreach ($columnApps as $app)
                        <div class="kanban-card" data-app-id="{{ $app->id }}">

                            {{-- Company + work type --}}
                            <div class="flex items-start justify-between gap-2" style="margin-bottom:0.375rem;">
                                <div class="flex items-center gap-2" style="min-width:0;">
                                    @php $initial = mb_strtoupper(mb_substr($app->company, 0, 1)); @endphp
                                    @if ($app->logo_url)
                                        <img src="{{ $app->logo_url }}"
                                             alt="{{ $app->company }}"
                                             class="co-logo"
                                             onerror="this.style.display='none';this.nextElementSibling.style.display='inline-flex';">
                                        <span class="co-badge" aria-hidden="true" style="display:none;">{{ $initial }}</span>
                                    @else
                                        <span class="co-badge" aria-hidden="true">{{ $initial }}</span>
                                    @endif
                                    <a href="{{ route('applications.show', $app) }}"
                                       class="font-semibold text-sm text-foreground"
                                       style="text-decoration:none;line-height:1.3;word-break:break-word;min-width:0;"
                                       title="{{ $app->company }}">{{ $app->company }}</a>
                                </div>
                                @if ($app->work_type)
                                    <span class="badge badge-outline" style="flex-shrink:0;">
                                        {{ $workTypes[$app->work_type] ?? ucfirst($app->work_type) }}
                                    </span>
                                @endif
                            </div>

                            {{-- Position --}}
                            <p class="text-xs text-muted" style="margin-bottom:0.5rem;line-height:1.35;">
                                {{ $app->position }}
                            </p>

                            {{-- Meta row: location + waiting time --}}
                            <div class="flex items-center gap-2" style="margin-bottom:0.5rem;flex-wrap:wrap;">
                                @if ($app->location)
                                    <span class="text-xs text-muted truncate" style="max-width:8rem;">{{ $app->location }}</span>
                                @endif
                                <span class="badge {{ $app->days_waiting > 14 ? 'badge-destructive' : ($app->days_waiting > 7 ? 'badge-warning' : 'badge-muted') }}">
                                    Menunggu {{ $app->days_waiting }} hari
                                </span>
                            </div>

                            @if ($app->salary_note)
                                <div style="margin-bottom:0.5rem;">
                                    <span class="text-xs text-muted">Gaji: <span class="font-medium text-foreground">{{ $app->salary_note }}</span></span>
                                </div>
                            @endif

                            {{-- Quick status changer --}}
                            <div class="border-t" style="padding-top:0.5rem;margin-top:auto;">
                                <form method="POST" action="{{ route('applications.quick-status', $app) }}">
                                    @csrf
                                    <label for="status-changer-{{ $app->id }}" class="sr-only">
                                        Ubah status {{ $app->company }}
                                    </label>
                                    <div class="flex items-center gap-1">
                                        <select id="status-changer-{{ $app->id }}"
                                                name="status"
                                                class="select select-compact"
                                                style="height:1.75rem;padding:0 1.75rem 0 0.5rem;flex:1;"
                                                onchange="this.form.submit()">
                                            @foreach ($columns as $sKey => $sMeta)
                                                <option value="{{ $sKey }}" {{ $app->status === $sKey ? 'selected' : '' }}>
                                                    {{ $sMeta['title'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button type="submit"
                                                class="btn btn-outline btn-sm"
                                                style="height:1.75rem;padding:0 0.5rem;flex-shrink:0;"
                                                title="Pindahkan tahap">
                                            Pindah
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    @endforeach
</div>
@endsection
