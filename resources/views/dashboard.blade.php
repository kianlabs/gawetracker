@extends('layouts.app')

@section('title', 'Dashboard')

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
@endphp

{{-- ── Page header ──────────────────────────────────────────────────────── --}}
<div class="page-header">
    <div class="page-header-meta">
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">Pantau pipeline lamaran, antrean tindak lanjut, dan konsistensi mingguan Anda</p>
    </div>
    <div class="page-actions">
        <button type="button"
                class="btn btn-default"
                onclick="openQuickAddModal()"
                id="btn-quick-add"
                title="Tekan Q di mana saja">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Quick Add
            <kbd class="kbd" style="background:hsl(var(--primary-foreground)/0.15);color:hsl(var(--primary-foreground));border:none;">Q</kbd>
        </button>
        <a href="{{ route('applications.create') }}" class="btn btn-outline">Tambah Lamaran</a>
        <a href="{{ route('applications.kanban') }}"  class="btn btn-outline">Kanban</a>
        <a href="{{ route('applications.index') }}"   class="btn btn-outline">Semua Lamaran</a>
    </div>
</div>

{{-- ── 1. Metric strip — 6 KPI cells ───────────────────────────────────── --}}
{{--
    Identity motif: 2px border-top marks each cell as a KPI surface.
    Accent color variant on border-top encodes the metric's semantic category.
    Reason: creates visual rhythm and allows scanning without color overload —
    the body remains achromatic while the top edge carries the signal.
--}}
<div class="metric-strip">
    <div class="metric-card accent-primary">
        <div class="metric-label">Total Lamaran</div>
        <div class="metric-value tabular-nums">{{ $total }}</div>
        <div class="metric-footer">Semua status</div>
    </div>

    <div class="metric-card accent-blue">
        <div class="metric-label">Aktif Diproses</div>
        <div class="metric-value tabular-nums">{{ $active }}</div>
        <div class="metric-footer flex items-center gap-1">
            <span class="status-dot blue"></span>
            Dalam pipeline
        </div>
    </div>

    <div class="metric-card accent-amber">
        <div class="metric-label">Interview</div>
        <div class="metric-value tabular-nums">{{ $interview }}</div>
        <div class="metric-footer flex items-center gap-1">
            <span class="status-dot amber"></span>
            Tahap wawancara
        </div>
    </div>

    <div class="metric-card accent-green">
        <div class="metric-label">Offering / Hired</div>
        <div class="metric-value tabular-nums">{{ $offer + $hired }}</div>
        <div class="metric-footer flex items-center gap-1">
            <span class="status-dot green"></span>
            {{ $offer }} offer &bull; {{ $hired }} diterima
        </div>
    </div>

    <div class="metric-card accent-purple">
        <div class="metric-label">Win Rate Interview</div>
        <div class="metric-value small tabular-nums" style="color:hsl(142 76% 30%);">{{ $win_rate }}%</div>
        <div class="metric-footer">Tembus wawancara</div>
    </div>

    <div class="metric-card" style="border-top-color:hsl(var(--foreground));">
        <div class="metric-label">Target Mingguan</div>
        <div class="metric-value small tabular-nums">
            {{ $weeklyCount }}<span class="text-xs font-normal text-muted" style="letter-spacing:0;">/{{ $weeklyTarget }}</span>
        </div>
        <div class="progress" style="margin-top:0.25rem;" role="progressbar"
             aria-valuenow="{{ $weeklyPercentage }}" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-indicator" style="width:{{ min(100,$weeklyPercentage) }}%;"></div>
        </div>
    </div>
</div>

@if ($expiringOffers->isNotEmpty())
<div class="offer-alert" role="alert">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="hsl(38 80% 35%)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="flex-shrink:0;margin-top:0.125rem;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <div class="offer-alert-content">
        <div class="offer-alert-title">{{ $expiringOffers->count() }} Offer Mendekati Deadline</div>
        @foreach ($expiringOffers as $offer)
        <div class="offer-alert-row">
            <span style="font-weight:600;">{{ $offer->jobApplication->company }}</span>
            <span>&nbsp;·&nbsp;</span>
            <span>{{ $offer->jobApplication->position }}</span>
            <span class="offer-alert-deadline">Deadline: {{ $offer->deadline_at->translatedFormat('d M Y') }}</span>
        </div>
        @endforeach
    </div>
    <a href="{{ route('offers.index') }}" class="btn btn-sm" style="flex-shrink:0;background:hsl(38 85% 50%);color:#fff;border:none;">Lihat Offer</a>
</div>
@endif

{{-- ── 2. Split workspace: Follow-up queue + Weekly target panel ────────── --}}
<div class="split-grid">

    {{-- Left (8/12): Perlu Follow-up --}}
    {{--
        Reason: action-required items at the top-left — F-pattern reading.
        Follow-up is the most time-sensitive decision: who needs a nudge today?
    --}}
    <div class="section-card">
        <div class="section-header">
            <div class="flex items-center gap-2">
                <h2 class="section-title">Perlu Follow-up</h2>
                @if ($followUpApplications->isNotEmpty())
                    <span class="badge badge-warning">{{ $followUpApplications->count() }}</span>
                @endif
            </div>
            <span class="text-xs text-muted">&gt;7 hari tanpa kabar</span>
        </div>

        <div class="section-body">
            @if ($followUpApplications->isEmpty())
                <div class="empty-state" style="padding:2rem 1.25rem;">
                    <div class="empty-state-icon" style="background:hsl(142 76% 94%);color:hsl(142 76% 28%);">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                    <p class="empty-state-title">Semua Terkendali</p>
                    <p class="empty-state-desc">Tidak ada lamaran yang menggantung. Semua proses terkontrol!</p>
                </div>
            @else
                @foreach ($followUpApplications as $app)
                    <div class="list-row" style="flex-wrap:wrap;">
                        {{-- Company identity --}}
                        <div class="flex items-center gap-2" style="min-width:160px;flex:1;">
                            @if ($app->logo_url)
                                <img src="{{ $app->logo_url }}"
                                     alt="{{ $app->company }}"
                                     class="co-logo"
                                     onerror="this.style.display='none';this.nextElementSibling.style.display='inline-flex';">
                                <span class="co-badge" aria-hidden="true" style="display:none;">{{ strtoupper(substr($app->company,0,1)) }}</span>
                            @else
                                <span class="co-badge" aria-hidden="true">{{ strtoupper(substr($app->company,0,1)) }}</span>
                            @endif
                            <div class="min-w-0">
                                <a href="{{ route('applications.show', $app) }}"
                                   class="text-sm font-semibold text-foreground truncate"
                                   style="text-decoration:none;display:block;"
                                   title="{{ $app->company }}">{{ $app->company }}</a>
                                <p class="text-xs text-muted truncate">{{ $app->position }}</p>
                            </div>
                        </div>

                        {{-- Badges --}}
                        <div class="flex items-center gap-1 flex-shrink-0">
                            <span class="badge badge-warning">{{ $app->days_waiting }} hari tanpa kabar</span>
                            <span class="badge {{ $badgeMap[$app->status] ?? 'badge-secondary' }}">
                                {{ $statuses[$app->status]['label'] ?? $app->status }}
                            </span>
                        </div>

                        {{-- Quick status update --}}
                        <form method="POST"
                              action="{{ route('applications.quick-status', $app) }}"
                              class="flex items-center gap-1 flex-shrink-0">
                            @csrf
                            <label for="status-followup-{{ $app->id }}" class="sr-only">
                                Ubah status {{ $app->company }}
                            </label>
                            <select id="status-followup-{{ $app->id }}"
                                    name="status"
                                    class="select"
                                    style="height:1.75rem;font-size:0.75rem;padding:0 1.5rem 0 0.5rem;width:auto;">
                                @foreach ($statuses as $code => $info)
                                    <option value="{{ $code }}" {{ $app->status === $code ? 'selected' : '' }}>
                                        {{ $info['label'] }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-secondary btn-sm">Simpan</button>
                        </form>
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    {{-- Right (4/12): Target Mingguan + navigasi modul --}}
    <div class="section-card" style="display:flex;flex-direction:column;">

        <div class="section-header">
            <h2 class="section-title">Target Mingguan</h2>
            <span class="tabular-nums text-xs font-semibold">{{ $weeklyCount }} / {{ $weeklyTarget }}</span>
        </div>

        <div style="padding:1rem 1.25rem;flex:1;">
            {{-- Progress visual --}}
            <div class="flex items-baseline justify-between mb-1">
                <span class="text-xs text-muted">Kemajuan pekan ini</span>
                <span class="text-xl font-bold tabular-nums">{{ $weeklyPercentage }}%</span>
            </div>
            <div class="progress mb-3" role="progressbar"
                 aria-valuenow="{{ $weeklyPercentage }}" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-indicator" style="width:{{ min(100,$weeklyPercentage) }}%;"></div>
            </div>

            <p class="text-xs text-muted mb-4" style="line-height:1.5;">
                <strong class="text-foreground">{{ $weeklyCount }}</strong> dari
                <strong class="text-foreground">{{ $weeklyTarget }}</strong> lamaran minggu ini.
                @if ($weeklyPercentage >= 100)
                    <span style="color:hsl(142 76% 28%);font-weight:600;">Target tercapai!</span>
                @elseif ($weeklyPercentage >= 60)
                    <span style="color:hsl(217 91% 40%);font-weight:600;">Hampir tercapai, terus kirim!</span>
                @else
                    <span class="text-muted">Rekomendasi: 5–10 lamaran/minggu.</span>
                @endif
            </p>

            {{-- Target setter --}}
            <form method="GET" action="{{ route('dashboard') }}"
                  class="flex items-center gap-2 pt-3 border-t">
                <label for="weekly-target-input" class="text-xs text-muted flex-shrink-0">Atur target:</label>
                <input type="number"
                       id="weekly-target-input"
                       name="target"
                       value="{{ $weeklyTarget }}"
                       min="1" max="100"
                       class="input"
                       style="width:56px;height:1.75rem;font-size:0.8125rem;padding:0 0.375rem;text-align:center;">
                <button type="submit" class="btn btn-secondary btn-sm">Simpan</button>
            </form>
        </div>

        {{-- Module navigation dock --}}
        <div style="border-top:1px solid hsl(var(--border));padding:0.625rem 0.75rem;">
            <p class="text-xs font-semibold text-muted uppercase tracking-wider" style="padding:0 0.25rem;margin-bottom:0.25rem;font-size:0.625rem;">Modul</p>
            <a href="{{ route('applications.kanban') }}"  class="nav-quick-link">Papan Kanban<span class="chevron">›</span></a>
            <a href="{{ route('analytics.index') }}"      class="nav-quick-link">Funnel &amp; Analisis<span class="chevron">›</span></a>
            <a href="{{ route('offers.index') }}"         class="nav-quick-link">Komparasi Offer<span class="chevron">›</span></a>
            <a href="{{ route('applications.export') }}"  class="nav-quick-link">Ekspor CSV<span class="chevron">↓</span></a>
        </div>
    </div>
</div>

{{-- ── 3. Lamaran Terbaru — data table ───────────────────────────────────── --}}
{{--
    Reason: raw data at bottom of stratified layout (abstractions at top,
    action queue in middle, raw records at bottom). Table not card-in-card.
--}}
<div class="section-card">
    <div class="section-header">
        <div>
            <h2 class="section-title">Lamaran Terbaru</h2>
            <p class="text-xs text-muted mt-1">5 lamaran yang paling baru Anda kirimkan</p>
        </div>
        <div class="flex items-center gap-1">
            <a href="{{ route('applications.index') }}" class="btn btn-outline btn-sm">Lihat Semua</a>
            <button type="button" class="btn btn-default btn-sm" onclick="openQuickAddModal()">+ Tambah</button>
        </div>
    </div>

    @if ($recentApplications->isEmpty())
        <div class="empty-state">
            <div class="empty-state-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            </div>
            <p class="empty-state-title">Belum ada lamaran</p>
            <p class="empty-state-desc">Tambahkan lamaran pertama Anda untuk mulai melacak pipeline rekrutmen.</p>
            <a href="{{ route('applications.create') }}" class="btn btn-default btn-sm">+ Tambah Lamaran</a>
        </div>
    @else
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Perusahaan</th>
                        <th>Posisi</th>
                        <th>Lokasi</th>
                        <th>Tipe</th>
                        <th>Tanggal</th>
                        <th>Status</th>
                        <th style="text-align:right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentApplications as $app)
                        <tr>
                            <td>
                                <div class="flex items-center gap-2">
                                    @if ($app->logo_url)
                                        <img src="{{ $app->logo_url }}"
                                             alt="{{ $app->company }}"
                                             class="co-logo"
                                             onerror="this.style.display='none';this.nextElementSibling.style.display='inline-flex';">
                                        <span class="co-badge" aria-hidden="true" style="display:none;">{{ strtoupper(substr($app->company,0,1)) }}</span>
                                    @else
                                        <span class="co-badge" aria-hidden="true">{{ strtoupper(substr($app->company,0,1)) }}</span>
                                    @endif
                                    <a href="{{ route('applications.show', $app) }}"
                                       class="text-sm font-medium text-foreground"
                                       style="text-decoration:none;">{{ $app->company }}</a>
                                </div>
                            </td>
                            <td><span class="text-sm text-muted">{{ $app->position }}</span></td>
                            <td><span class="text-sm text-muted">{{ $app->location ?: '—' }}</span></td>
                            <td>
                                @if ($app->work_type)
                                    <span class="badge badge-outline capitalize">{{ $app->work_type }}</span>
                                @else
                                    <span class="text-sm text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="text-sm text-muted tabular-nums">
                                    {{ $app->applied_at ? $app->applied_at->format('d M Y') : '—' }}
                                </span>
                            </td>
                            <td>
                                <span class="badge {{ $badgeMap[$app->status] ?? 'badge-secondary' }}">
                                    {{ $statuses[$app->status]['label'] ?? $app->status }}
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <div class="flex items-center justify-end gap-1">
                                    <form method="POST"
                                          action="{{ route('applications.quick-status', $app) }}"
                                          class="flex items-center gap-1">
                                        @csrf
                                        <label for="status-recent-{{ $app->id }}" class="sr-only">
                                            Ubah status {{ $app->company }}
                                        </label>
                                        <select id="status-recent-{{ $app->id }}"
                                                name="status"
                                                class="select"
                                                style="height:1.75rem;font-size:0.75rem;padding:0 1.5rem 0 0.5rem;width:auto;">
                                            @foreach ($statuses as $code => $info)
                                                <option value="{{ $code }}" {{ $app->status === $code ? 'selected' : '' }}>
                                                    {{ $info['label'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="btn btn-secondary btn-sm">Simpan</button>
                                    </form>
                                    <a href="{{ route('applications.show', $app) }}" class="btn btn-outline btn-sm">Detail</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- ── Quick Add Modal (<15s) ────────────────────────────────────────────── --}}
<div id="quickAddModal"
     class="dialog-backdrop"
     role="dialog"
     aria-modal="true"
     aria-labelledby="quickAddTitle">
    <div class="dialog-content">
        <button type="button"
                class="dialog-close"
                onclick="closeQuickAddModal()"
                aria-label="Tutup">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>

        <div class="dialog-header">
            <h2 class="dialog-title" id="quickAddTitle">Quick Add Lamaran</h2>
            <p class="dialog-description">Catat lowongan baru dalam hitungan detik (&lt;15 detik).</p>
        </div>

        <form method="POST" action="{{ route('applications.quick-store') }}" id="quickAddForm">
            @csrf
            <div class="space-y-4">
                <div>
                    <label for="quick_company" class="label">
                        Nama Perusahaan <span style="color:hsl(var(--destructive));" aria-hidden="true">*</span>
                    </label>
                    <input type="text"
                           id="quick_company"
                           name="company"
                           class="input @error('company') input-error @enderror"
                           required
                           autocomplete="organization"
                           placeholder="Mis: PT Gojek Indonesia"
                           value="{{ old('company') }}">
                    @error('company')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="quick_position" class="label">
                        Posisi <span style="color:hsl(var(--destructive));" aria-hidden="true">*</span>
                    </label>
                    <input type="text"
                           id="quick_position"
                           name="position"
                           class="input @error('position') input-error @enderror"
                           required
                           placeholder="Mis: Senior Backend Engineer"
                           value="{{ old('position') }}">
                    @error('position')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="quick_source_url" class="label">
                        Link Lowongan
                        <span class="text-xs font-normal text-muted">(opsional)</span>
                    </label>
                    <input type="url"
                           id="quick_source_url"
                           name="source_url"
                           class="input @error('source_url') input-error @enderror"
                           placeholder="https://linkedin.com/jobs/..."
                           value="{{ old('source_url') }}">
                    @error('source_url')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="quick_work_type" class="label">Tipe Kerja</label>
                        <select id="quick_work_type" name="work_type"
                                class="select @error('work_type') select-error @enderror">
                            <option value="">Pilih tipe</option>
                            <option value="remote"  {{ old('work_type') === 'remote'  ? 'selected' : '' }}>Remote</option>
                            <option value="hybrid"  {{ old('work_type') === 'hybrid'  ? 'selected' : '' }}>Hybrid</option>
                            <option value="onsite"  {{ old('work_type') === 'onsite'  ? 'selected' : '' }}>Onsite</option>
                        </select>
                    </div>
                    <div>
                        <label for="quick_status" class="label">Status Awal</label>
                        <select id="quick_status" name="status"
                                class="select @error('status') select-error @enderror">
                            <option value="wishlist" {{ old('status') === 'wishlist' ? 'selected' : '' }}>Wishlist</option>
                            <option value="applied"  {{ old('status','applied') === 'applied' ? 'selected' : '' }}>Terkirim</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="dialog-footer">
                <button type="button" class="btn btn-outline" onclick="closeQuickAddModal()">Batal</button>
                <button type="submit" class="btn btn-default">Simpan Kilat</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    'use strict';

    function openQuickAddModal() {
        var m = document.getElementById('quickAddModal');
        if (!m) return;
        m.classList.add('open');
        m.setAttribute('aria-hidden', 'false');
        setTimeout(function () {
            var f = document.getElementById('quick_company');
            if (f) f.focus();
        }, 80);
    }

    function closeQuickAddModal() {
        var m = document.getElementById('quickAddModal');
        if (!m) return;
        m.classList.remove('open');
        m.setAttribute('aria-hidden', 'true');
        document.getElementById('btn-quick-add')?.focus();
    }

    window.openQuickAddModal  = openQuickAddModal;
    window.closeQuickAddModal = closeQuickAddModal;

    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('quickAddModal');

        // Backdrop click closes
        if (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target === modal) closeQuickAddModal();
            });
        }

        // ESC closes; Q opens
        document.addEventListener('keydown', function (e) {
            var tag = document.activeElement ? document.activeElement.tagName : '';
            var inField = ['INPUT', 'TEXTAREA', 'SELECT'].indexOf(tag) !== -1;

            if (e.key === 'Escape' && modal && modal.classList.contains('open')) {
                e.preventDefault();
                closeQuickAddModal();
            }
            if ((e.key === 'q' || e.key === 'Q') && !inField && modal && !modal.classList.contains('open')) {
                e.preventDefault();
                openQuickAddModal();
            }
        });

        // Re-open on validation error
        @if ($errors->has('company') || $errors->has('position') || $errors->has('source_url') || $errors->has('work_type') || $errors->has('status'))
        openQuickAddModal();
        @endif
    });
}());
</script>
@endsection
