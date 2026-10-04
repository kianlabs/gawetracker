@extends('layouts.app')

@section('title', 'Daftar Lamaran Kerja')

@section('content')
{{-- Page header --}}
<div class="page-header">
    <div class="page-header-meta">
        <h1 class="page-title">Daftar Lamaran Kerja</h1>
        <p class="page-subtitle">Pipeline lengkap semua lamaran yang sudah Anda daftarkan</p>
    </div>
    <div class="page-actions">
        <a href="{{ route('applications.export', request()->query()) }}" class="btn btn-outline">Ekspor CSV</a>
        <a href="{{ route('applications.create') }}" class="btn btn-default">+ Tambah Lamaran</a>
    </div>
</div>

{{-- Filter bar --}}
<div class="section-card mb-4">
    <form method="GET" action="{{ route('applications.index') }}">
        <div class="filter-bar">
            <div style="display:flex;flex-direction:column;gap:0.25rem;min-width:14rem;flex:2;">
                <label for="search" class="label text-xs font-semibold text-muted" style="text-transform:uppercase;letter-spacing:.04em;">Cari</label>
                <input
                    type="text"
                    id="search"
                    name="search"
                    class="input"
                    placeholder="Perusahaan, posisi, lokasi…"
                    value="{{ $currentSearch }}"
                >
            </div>

            <div style="display:flex;flex-direction:column;gap:0.25rem;min-width:9rem;flex:1;">
                <label for="status" class="label text-xs font-semibold text-muted" style="text-transform:uppercase;letter-spacing:.04em;">Status</label>
                <select id="status" name="status" class="select">
                    <option value="">Semua Status</option>
                    @foreach ($statuses as $key => $meta)
                        <option value="{{ $key }}" {{ $currentStatus === $key ? 'selected' : '' }}>
                            {{ $meta['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="display:flex;flex-direction:column;gap:0.25rem;min-width:9rem;flex:1;">
                <label for="work_type" class="label text-xs font-semibold text-muted" style="text-transform:uppercase;letter-spacing:.04em;">Tipe Kerja</label>
                <select id="work_type" name="work_type" class="select">
                    <option value="">Semua Tipe</option>
                    @foreach ($workTypes as $key => $label)
                        <option value="{{ $key }}" {{ $currentWorkType === $key ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="display:flex;flex-direction:column;gap:0.25rem;min-width:8rem;flex:1;">
                <label for="sort_by" class="label text-xs font-semibold text-muted" style="text-transform:uppercase;letter-spacing:.04em;">Urutkan</label>
                <select id="sort_by" name="sort_by" class="select">
                    <option value="applied_at" {{ $currentSortBy === 'applied_at' ? 'selected' : '' }}>Tgl Melamar</option>
                    <option value="company" {{ $currentSortBy === 'company' ? 'selected' : '' }}>Perusahaan</option>
                    <option value="created_at" {{ $currentSortBy === 'created_at' ? 'selected' : '' }}>Tgl Ditambahkan</option>
                    <option value="status" {{ $currentSortBy === 'status' ? 'selected' : '' }}>Status</option>
                </select>
            </div>

            <div style="display:flex;flex-direction:column;gap:0.25rem;min-width:6rem;flex:0 0 auto;">
                <label for="sort_dir" class="label text-xs font-semibold text-muted" style="text-transform:uppercase;letter-spacing:.04em;">Arah</label>
                <select id="sort_dir" name="sort_dir" class="select">
                    <option value="desc" {{ $currentSortDir === 'desc' ? 'selected' : '' }}>Menurun</option>
                    <option value="asc" {{ $currentSortDir === 'asc' ? 'selected' : '' }}>Menaik</option>
                </select>
            </div>

            <div style="display:flex;align-items:flex-end;gap:0.5rem;padding-bottom:0.0625rem;">
                <button type="submit" class="btn btn-default btn-sm">Filter</button>
                @php
                    $activeFilterCount = collect(['search' => $currentSearch, 'status' => $currentStatus, 'work_type' => $currentWorkType])
                        ->filter(fn($val) => $val !== '' && $val !== null)
                        ->count();
                @endphp
                <a href="{{ route('applications.index') }}" class="btn btn-outline btn-sm">
                    Reset
                    @if ($activeFilterCount > 0)
                        <span class="badge badge-secondary" style="margin-left:0.375rem;">{{ $activeFilterCount }}</span>
                    @endif
                </a>
            </div>
        </div>
    </form>
</div>

{{-- Main table --}}
@if ($applications->count() > 0)
    <div class="section-card">
        <div class="section-header">
            <span class="section-title">
                {{ $applications->total() }} lamaran ditemukan
            </span>
            <span class="text-xs text-muted tabular-nums">
                Halaman {{ $applications->currentPage() }} dari {{ $applications->lastPage() }}
            </span>
        </div>
        <div class="section-body">
            <div class="table-wrapper table-wrapper--actions">
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
                        @foreach ($applications as $app)
                            @php
                                $badgeMap = [
                                    'wishlist'  => 'badge-secondary',
                                    'applied'   => 'badge-info',
                                    'screening' => 'badge-warning',
                                    'interview' => 'badge-warning',
                                    'offer'     => 'badge-purple',
                                    'hired'     => 'badge-success',
                                    'rejected'  => 'badge-destructive',
                                ];
                                $badgeVariant = $badgeMap[$app->status] ?? 'badge-secondary';
                                $statusLabel  = $statuses[$app->status]['label'] ?? ucfirst($app->status);
                                $initial      = mb_strtoupper(mb_substr($app->company, 0, 1));
                            @endphp
                            <tr>
                                <td>
                                    <div class="flex items-center gap-2">
                                        @if ($app->logo_url)
                                            <img src="{{ $app->logo_url }}"
                                                 alt="{{ $app->company }}"
                                                 class="co-logo"
                                                 onerror="this.style.display='none';this.nextElementSibling.style.display='inline-flex';">
                                            <span class="co-badge" aria-hidden="true" style="display:none;">{{ $initial }}</span>
                                        @else
                                            <span class="co-badge" aria-hidden="true">{{ $initial }}</span>
                                        @endif
                                        <div>
                                            <a href="{{ route('applications.show', $app) }}" class="font-semibold" style="color:hsl(var(--foreground));text-decoration:none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
                                                {{ $app->company }}
                                            </a>
                                            @if ($app->source || $app->source_url)
                                                <div class="text-xs text-muted" style="margin-top:0.125rem;">
                                                    @if ($app->source_url)
                                                        <a href="{{ $app->source_url }}" target="_blank" rel="noopener noreferrer" class="text-muted" style="text-decoration:none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
                                                            {{ $app->source ?: 'Tautan Lowongan' }} &nearr;
                                                        </a>
                                                    @else
                                                        <span>{{ $app->source }}</span>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <a href="{{ route('applications.show', $app) }}" class="text-muted" style="text-decoration:none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
                                            {{ $app->position }}
                                        </a>
                                    </div>
                                    @if ($app->salary_note)
                                        <div class="text-xs text-muted" style="margin-top:0.125rem;">{{ $app->salary_note }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="text-sm text-muted">{{ $app->location ?: '—' }}</span>
                                </td>
                                <td>
                                    @if ($app->work_type)
                                        <span class="badge badge-outline text-xs">
                                            {{ $workTypes[$app->work_type] ?? ucfirst($app->work_type) }}
                                        </span>
                                    @else
                                        <span class="text-xs text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="text-sm text-muted tabular-nums">
                                        {{ $app->applied_at ? $app->applied_at->format('d M Y') : '—' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $badgeVariant }}">{{ $statusLabel }}</span>
                                </td>
                                <td style="text-align:right;">
                                    <div class="flex items-center justify-end gap-1" style="gap:0.375rem;">
                                        <a href="{{ route('applications.show', $app) }}" class="btn btn-outline btn-sm">Detail</a>
                                        <a href="{{ route('applications.edit', $app) }}" class="btn btn-ghost btn-sm">Edit</a>
                                        <form method="POST" action="{{ route('applications.destroy', $app) }}" onsubmit="return confirm('Hapus lamaran {{ addslashes($app->company) }} – {{ addslashes($app->position) }}? Semua riwayat status juga akan dihapus.')" style="display:inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-destructive btn-sm">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Pagination --}}
    @if ($applications->hasPages())
        <div class="pagination-wrapper" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-top:1rem;">
            <span class="pagination-info">{{ $applications->total() }} lamaran ditemukan</span>
            <div class="pagination-links">
                {{ $applications->links() }}
            </div>
        </div>
    @else
        <div class="pagination-info" style="margin-top:0.75rem;">{{ $applications->total() }} lamaran ditemukan</div>
    @endif

@else
    {{-- Empty state --}}
    <div class="section-card">
        <div class="empty-state">
            <div class="empty-state-title">Belum ada lamaran</div>
            <p class="empty-state-desc">
                @if ($currentSearch || $currentStatus || $currentWorkType)
                    Tidak ada lamaran yang cocok dengan filter saat ini. Coba ubah kriteria pencarian atau reset filter.
                @else
                    Coba ubah filter pencarian atau tambahkan lamaran baru.
                @endif
            </p>
            @if ($currentSearch || $currentStatus || $currentWorkType)
                <a href="{{ route('applications.index') }}" class="btn btn-outline">Reset Filter</a>
            @else
                <a href="{{ route('applications.create') }}" class="btn btn-default">+ Tambah Lamaran</a>
            @endif
        </div>
    </div>
@endif
@endsection
