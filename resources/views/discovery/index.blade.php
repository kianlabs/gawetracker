@extends('layouts.app')

@section('title', 'Cari Kerja')

@section('content')
{{-- Page header --}}
<div class="page-header">
    <div class="page-header-meta">
        <h1 class="page-title">Cari Kerja</h1>
        <p class="page-subtitle">Telusuri lowongan langsung dari Glints &amp; JobStreet, lalu pindahkan yang menarik ke daftar lamaran</p>
    </div>
</div>

{{-- Live search form --}}
<div class="section-card mb-4">
    <div class="section-header">
        <span class="section-title">Pencarian Lowongan</span>
        <span class="text-xs text-muted">{{ $totalPostings }} lowongan tersimpan &bull; {{ $newPostings }} belum dilamar</span>
    </div>
    <div class="section-body">
        <form method="POST" action="{{ route('discovery.search') }}">
            @csrf
            <div class="filter-bar">
                <div style="display:flex;flex-direction:column;gap:0.25rem;min-width:14rem;flex:2;">
                    <label for="keyword" class="label text-xs font-semibold text-muted" style="text-transform:uppercase;letter-spacing:.04em;">Kata Kunci</label>
                    <input
                        type="text"
                        id="keyword"
                        name="keyword"
                        class="input"
                        placeholder="Contoh: backend developer, data analyst…"
                        value="{{ old('keyword') }}"
                        required
                    >
                    @error('keyword')
                        <span class="input-error text-xs">{{ $message }}</span>
                    @enderror
                </div>

                <div style="display:flex;flex-direction:column;gap:0.25rem;min-width:8rem;flex:0 0 auto;">
                    <label for="limit" class="label text-xs font-semibold text-muted" style="text-transform:uppercase;letter-spacing:.04em;">Maks / Sumber</label>
                    <select id="limit" name="limit" class="select">
                        @foreach ([10, 20, 30, 60] as $opt)
                            <option value="{{ $opt }}" {{ (int) old('limit', 30) === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                        @endforeach
                    </select>
                    @error('limit')
                        <span class="input-error text-xs">{{ $message }}</span>
                    @enderror
                </div>

                <div style="display:flex;align-items:flex-end;gap:0.5rem;padding-bottom:0.0625rem;">
                    <button type="submit" class="btn btn-default btn-sm">Cari Sekarang</button>
                </div>
            </div>
            <p class="text-xs text-muted" style="margin-top:0.75rem;">
                Pencarian mengambil data langsung dari Glints &amp; JobStreet secara real-time dan bisa memakan waktu beberapa detik.
            </p>
        </form>
    </div>
</div>

{{-- Filter bar --}}
<div class="section-card mb-4">
    <form method="GET" action="{{ route('discovery.index') }}">
        <div class="filter-bar">
            <div style="display:flex;flex-direction:column;gap:0.25rem;min-width:14rem;flex:2;">
                <label for="search" class="label text-xs font-semibold text-muted" style="text-transform:uppercase;letter-spacing:.04em;">Cari di Temuan</label>
                <input
                    type="text"
                    id="search"
                    name="search"
                    class="input"
                    placeholder="Posisi, perusahaan, lokasi…"
                    value="{{ $currentSearch }}"
                >
            </div>

            <div style="display:flex;flex-direction:column;gap:0.25rem;min-width:9rem;flex:1;">
                <label for="source" class="label text-xs font-semibold text-muted" style="text-transform:uppercase;letter-spacing:.04em;">Sumber</label>
                <select id="source" name="source" class="select">
                    <option value="">Semua Sumber</option>
                    @foreach ($sources as $key => $label)
                        <option value="{{ $key }}" {{ $currentSource === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div style="display:flex;flex-direction:column;gap:0.25rem;min-width:9rem;flex:1;">
                <label for="promoted" class="label text-xs font-semibold text-muted" style="text-transform:uppercase;letter-spacing:.04em;">Status</label>
                <select id="promoted" name="promoted" class="select">
                    <option value="">Semua</option>
                    <option value="no" {{ $currentPromoted === 'no' ? 'selected' : '' }}>Belum Dilamar</option>
                    <option value="yes" {{ $currentPromoted === 'yes' ? 'selected' : '' }}>Sudah Dilamar</option>
                </select>
            </div>

            <div style="display:flex;align-items:flex-end;gap:0.5rem;padding-bottom:0.0625rem;">
                <button type="submit" class="btn btn-default btn-sm">Filter</button>
                <a href="{{ route('discovery.index') }}" class="btn btn-outline btn-sm">Reset</a>
            </div>
        </div>
    </form>
</div>

{{-- Results --}}
@if ($postings->count() > 0)
    <div class="section-card">
        <div class="section-header">
            <span class="section-title">{{ $postings->total() }} lowongan ditemukan</span>
            <span class="text-xs text-muted tabular-nums">
                Halaman {{ $postings->currentPage() }} dari {{ $postings->lastPage() }}
            </span>
        </div>
        <div class="section-body">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Posisi</th>
                            <th>Perusahaan</th>
                            <th>Lokasi</th>
                            <th>Gaji</th>
                            <th>Sumber</th>
                            <th style="text-align:right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($postings as $posting)
                            @php
                                $initial = mb_strtoupper(mb_substr($posting->company ?: '?', 0, 1));
                                $sourceLabel = $sources[$posting->source] ?? ucfirst($posting->source);
                                $postedLabel = $posting->posted_at ? $posting->posted_at->diffForHumans() : null;
                            @endphp
                            <tr>
                                <td>
                                    <div>
                                        @if ($posting->source_url)
                                            <a href="{{ $posting->source_url }}" target="_blank" rel="noopener noreferrer"
                                               class="font-semibold" style="color:hsl(var(--foreground));text-decoration:none;"
                                               onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
                                                {{ $posting->title }} &nearr;
                                            </a>
                                        @else
                                            <span class="font-semibold">{{ $posting->title }}</span>
                                        @endif
                                    </div>
                                    @if ($postedLabel)
                                        <div class="text-xs text-muted" style="margin-top:0.125rem;">Diposting {{ $postedLabel }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <span class="co-badge" aria-hidden="true">{{ $initial }}</span>
                                        <span>{{ $posting->company ?: '—' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-sm text-muted">{{ $posting->location ?: '—' }}</span>
                                </td>
                                <td>
                                    @if ($posting->salary_note)
                                        <span class="text-sm tabular-nums">{{ $posting->salary_note }}</span>
                                    @else
                                        <span class="text-xs text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-outline text-xs">{{ $sourceLabel }}</span>
                                </td>
                                <td style="text-align:right;">
                                    <div class="flex items-center justify-end gap-1" style="gap:0.375rem;">
                                        @if ($posting->isPromoted())
                                            <a href="{{ route('applications.show', $posting->job_application_id) }}" class="btn btn-outline btn-sm">
                                                Lihat Lamaran
                                            </a>
                                        @else
                                            <form method="POST" action="{{ route('discovery.promote', $posting) }}" style="display:inline;">
                                                @csrf
                                                <button type="submit" class="btn btn-default btn-sm">+ Lamar</button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('discovery.destroy', $posting) }}"
                                              onsubmit="return confirm('Hapus lowongan {{ addslashes($posting->title) }} dari daftar temuan?')"
                                              style="display:inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-ghost btn-sm">Hapus</button>
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
    @if ($postings->hasPages())
        <div class="pagination-wrapper" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-top:1rem;">
            <span class="pagination-info">{{ $postings->total() }} lowongan ditemukan</span>
            <div class="pagination-links">
                {{ $postings->links() }}
            </div>
        </div>
    @endif
@else
    {{-- Empty state --}}
    <div class="section-card">
        <div class="empty-state">
            <div class="empty-state-title">
                @if ($currentSearch || $currentSource || $currentPromoted)
                    Tidak ada lowongan yang cocok
                @else
                    Belum ada lowongan tersimpan
                @endif
            </div>
            <p class="empty-state-desc">
                @if ($currentSearch || $currentSource || $currentPromoted)
                    Coba ubah filter atau reset untuk melihat semua temuan.
                @else
                    Gunakan formulir di atas untuk mencari lowongan dari Glints &amp; JobStreet.
                    Hasilnya akan tersimpan di sini.
                @endif
            </p>
            @if ($currentSearch || $currentSource || $currentPromoted)
                <a href="{{ route('discovery.index') }}" class="btn btn-outline">Reset Filter</a>
            @endif
        </div>
    </div>
@endif
@endsection
