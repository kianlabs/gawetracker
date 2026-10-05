@extends('layouts.app')

@section('title', $application->company . ' — ' . $application->position)

@section('styles')
<style>
    .info-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.25rem 1.5rem;
        padding: 1.25rem;
    }
    @media (min-width: 640px) {
        .info-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    .info-item-full { grid-column: 1 / -1; }

    .info-label {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: hsl(var(--muted-foreground));
        margin-bottom: 0.25rem;
    }
    .info-value {
        font-size: 0.875rem;
        color: hsl(var(--foreground));
        line-height: 1.45;
    }

    .timeline-body {
        padding-bottom: 0.125rem;
    }
    .timeline-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 0.375rem;
    }
    .timeline-note {
        font-size: 0.8125rem;
        color: hsl(var(--muted-foreground));
        line-height: 1.5;
    }

    .checklist-row {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.625rem 1.25rem;
        border-bottom: 1px solid hsl(var(--border));
    }
    .checklist-row:last-child { border-bottom: none; }
    .checklist-row:hover { background-color: hsl(240 4.8% 98%); }
    .checklist-row.completed { opacity: 0.6; }

    .custom-checkbox {
        flex-shrink: 0;
        width: 1.125rem;
        height: 1.125rem;
        border-radius: calc(var(--radius) - 2px);
        border: 1px solid hsl(var(--input));
        background-color: hsl(var(--background));
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 700;
        color: hsl(var(--primary-foreground));
        cursor: pointer;
        transition: background-color 0.12s, border-color 0.12s;
    }
    .custom-checkbox.checked {
        background-color: hsl(var(--primary));
        border-color: hsl(var(--primary));
    }

    .notes-pre {
        font-size: 0.875rem;
        line-height: 1.6;
        color: hsl(var(--foreground));
        word-break: break-word;
        white-space: pre-wrap;
        margin: 0;
        font-family: inherit;
    }

    .status-form-row {
        display: flex;
        gap: 0.5rem;
        align-items: flex-end;
    }
    .status-form-row .select { flex: 1; min-width: 0; }

    .section-footer {
        padding: 0.875rem 1.25rem;
        border-top: 1px solid hsl(var(--border));
        background-color: hsl(var(--muted) / 0.3);
    }

    .offer-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem 1.5rem;
        padding: 1.25rem;
    }
    .offer-grid-full { grid-column: 1 / -1; }

    .offer-form-section {
        padding: 1.25rem;
        border-top: 1px solid hsl(var(--border));
    }
    .offer-form-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.875rem;
        margin-bottom: 1rem;
    }
    @media (min-width: 640px) {
        .offer-form-grid { grid-template-columns: repeat(2, 1fr); }
    }
</style>
@endsection

@section('content')
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
    $currentBadge = $badgeMap[$application->status] ?? 'badge-secondary';
    $currentLabel = $statuses[$application->status]['label'] ?? $application->status;
@endphp

{{-- Page Header --}}
<div class="page-header">
    <div class="page-header-meta" style="display:flex;align-items:center;gap:0.75rem;">
        @if ($application->logo_url)
            <img src="{{ $application->logo_url }}"
                 alt="{{ $application->company }}"
                 class="co-logo"
                 style="width:2.75rem;height:2.75rem;padding:0.25rem;"
                 onerror="this.style.display='none';">
        @endif
        <div>
            <h1 class="page-title">{{ $application->company }}</h1>
            <p class="page-subtitle">{{ $application->position }}</p>
        </div>
    </div>
    <div class="page-actions" style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
        <span class="badge {{ $currentBadge }}">{{ $currentLabel }}</span>
        <a href="{{ route('applications.edit', $application) }}" class="btn btn-outline btn-sm">Edit</a>
        <a href="{{ route('applications.index') }}" class="btn btn-ghost btn-sm">Kembali</a>
    </div>
</div>

{{-- Two-column layout --}}
<div class="split-grid" style="margin-top: 1.25rem;">

    {{-- LEFT COLUMN --}}
    <div style="display: flex; flex-direction: column; gap: 1.25rem;">

        {{-- 1. Informasi Lamaran --}}
        <div class="section-card">
            <div class="section-header">
                <span class="section-title">Informasi Lamaran</span>
            </div>
            <div class="section-body">
                <div class="info-grid">
                    <div>
                        <div class="info-label">Perusahaan</div>
                        <div class="info-value">{{ $application->company }}</div>
                    </div>
                    <div>
                        <div class="info-label">Posisi</div>
                        <div class="info-value">{{ $application->position }}</div>
                    </div>
                    <div>
                        <div class="info-label">Lokasi</div>
                        <div class="info-value">{{ $application->location ?: '—' }}</div>
                    </div>
                    <div>
                        <div class="info-label">Tipe Kerja</div>
                        <div class="info-value">
                            @if ($application->work_type)
                                <span class="badge badge-secondary">{{ $workTypes[$application->work_type] ?? ucfirst($application->work_type) }}</span>
                            @else
                                —
                            @endif
                        </div>
                    </div>
                    <div>
                        <div class="info-label">Tanggal Melamar</div>
                        <div class="info-value">{{ $application->applied_at ? $application->applied_at->format('d M Y') : '—' }}</div>
                    </div>
                    <div>
                        <div class="info-label">Sumber Lowongan</div>
                        <div class="info-value">{{ $application->source ?: '—' }}</div>
                    </div>
                    @if ($application->source_url)
                    <div class="info-item-full">
                        <div class="info-label">Tautan Lowongan</div>
                        <div class="info-value">
                            <a href="{{ $application->source_url }}" target="_blank" rel="noopener noreferrer" style="word-break: break-all; color: hsl(var(--primary)); text-decoration: underline;">
                                {{ $application->source_url }}
                            </a>
                        </div>
                    </div>
                    @endif
                    @if ($application->salary_note)
                    <div class="info-item-full">
                        <div class="info-label">Estimasi Gaji / Kompensasi</div>
                        <div class="info-value">{{ $application->salary_note }}</div>
                    </div>
                    @endif
                    <div>
                        <div class="info-label">Nama Kontak HR</div>
                        <div class="info-value">{{ $application->contact_name ?: '—' }}</div>
                    </div>
                    <div>
                        <div class="info-label">Info Kontak</div>
                        <div class="info-value">{{ $application->contact_info ?: '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- 2. Riwayat Status --}}
        <div class="section-card">
            <div class="section-header">
                <span class="section-title">Timeline Riwayat Status</span>
                <span class="badge badge-secondary">{{ $application->statusHistories->count() }} catatan</span>
            </div>
            <div class="section-body" style="padding: 1.25rem;">
                @if ($application->statusHistories->isNotEmpty())
                    @foreach ($application->statusHistories as $history)
                        @php
                            $fromMeta = $history->from_status ? ($statuses[$history->from_status] ?? ['label' => $history->from_status]) : null;
                            $toMeta   = $statuses[$history->to_status] ?? ['label' => $history->to_status];
                            $fromBadge = $badgeMap[$history->from_status] ?? 'badge-secondary';
                            $toBadge   = $badgeMap[$history->to_status]   ?? 'badge-secondary';
                            $dotClass  = $loop->first ? 'active' : 'completed';
                        @endphp
                        <div class="timeline-item">
                            <div class="timeline-dot {{ $dotClass }}"></div>
                            <div class="timeline-body">
                                <div class="timeline-meta">
                                    <div style="display: inline-flex; align-items: center; gap: 0.375rem; flex-wrap: wrap;">
                                        @if ($fromMeta && $history->from_status !== $history->to_status)
                                            <span class="badge {{ $fromBadge }}">{{ $fromMeta['label'] }}</span>
                                            <span style="color: hsl(var(--muted-foreground)); font-size: 0.8125rem;">&rarr;</span>
                                        @endif
                                        <span class="badge {{ $toBadge }}">{{ $toMeta['label'] }}</span>
                                    </div>
                                    <time class="text-xs text-muted">
                                        {{ $history->created_at ? $history->created_at->format('d M Y, H:i') : '—' }}
                                    </time>
                                </div>
                                @if ($history->note)
                                    <div class="timeline-note">{{ $history->note }}</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="empty-state" style="padding: 2rem 1rem;">
                        <div class="empty-state-title">Belum ada riwayat status</div>
                        <div class="empty-state-desc">Gunakan formulir Perbarui Status di kolom kanan untuk mencatat perubahan status lamaran ini.</div>
                    </div>
                @endif
            </div>

            {{-- Quick status update in section footer --}}
            <div class="section-footer">
                <form method="POST" action="{{ route('applications.quick-status', $application) }}">
                    @csrf
                    <div class="info-label" style="margin-bottom: 0.5rem;">Ubah Status Cepat</div>
                    <div class="status-form-row">
                        <select id="quick_status" name="status" class="select" required>
                            @foreach ($statuses as $key => $meta)
                                <option value="{{ $key }}" {{ old('status', $application->status) === $key ? 'selected' : '' }}>
                                    {{ $meta['label'] }}
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-default btn-sm" style="white-space: nowrap;">Simpan</button>
                    </div>
                    @if ($errors->has('status'))
                        <span class="field-error">{{ $errors->first('status') }}</span>
                    @endif
                    <div style="margin-top: 0.5rem;">
                        <input
                            type="text"
                            id="quick_note"
                            name="note"
                            class="input"
                            placeholder="Catatan perubahan (opsional)"
                            value="{{ old('status') ? old('note') : '' }}"
                        >
                        @if ($errors->has('note') && old('status'))
                            <span class="field-error">{{ $errors->first('note') }}</span>
                        @endif
                    </div>
                </form>
            </div>
        </div>

    </div>{{-- /left --}}

    {{-- RIGHT COLUMN --}}
    <div style="display: flex; flex-direction: column; gap: 1.25rem;">

        {{-- 3. Interview Checklist --}}
        <div class="section-card" id="interview-checklist-card">
            <div class="section-header">
                <span class="section-title">Checklist Persiapan Interview</span>
                @php
                    $totalChecklists     = $application->interviewChecklists->count();
                    $completedChecklists = $application->interviewChecklists->where('is_completed', true)->count();
                @endphp
                @if ($totalChecklists > 0)
                    <span class="badge {{ $completedChecklists === $totalChecklists ? 'badge-success' : 'badge-info' }}">
                        {{ $completedChecklists }}/{{ $totalChecklists }}
                    </span>
                @endif
            </div>

            <div class="section-body">
                @if ($application->interviewChecklists->isNotEmpty())
                    @foreach ($application->interviewChecklists as $item)
                        <div class="checklist-row {{ $item->is_completed ? 'completed' : '' }}">
                            <form method="POST" action="{{ route('checklists.toggle', $item) }}" style="display: inline-flex; margin: 0; flex-shrink: 0;">
                                @csrf
                                @method('PATCH')
                                <button type="submit" style="background: none; border: none; padding: 0; cursor: pointer; display: inline-flex; align-items: center;" title="{{ $item->is_completed ? 'Batalkan selesai' : 'Tandai selesai' }}">
                                    <span class="custom-checkbox {{ $item->is_completed ? 'checked' : '' }}">
                                        @if ($item->is_completed)&#10003;@endif
                                    </span>
                                </button>
                            </form>
                            <div style="flex: 1; min-width: 0;">
                                <span class="text-sm {{ $item->is_completed ? 'text-muted' : '' }}" style="{{ $item->is_completed ? 'text-decoration: line-through;' : '' }}">
                                    {{ $item->title }}
                                </span>
                                @if ($item->is_completed && $item->completed_at)
                                    <span class="text-xs text-muted" style="display: block;">Selesai {{ $item->completed_at->format('d M, H:i') }}</span>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('checklists.destroy', $item) }}" onsubmit="return confirm('Hapus item checklist ini?');" style="display: inline-flex; margin: 0; flex-shrink: 0;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-sm text-muted" style="padding: 0 0.5rem; height: 1.75rem;" title="Hapus item">&times;</button>
                            </form>
                        </div>
                    @endforeach
                @else
                    <div class="empty-state" style="padding: 1.5rem 1rem;">
                        <div class="empty-state-title">Belum ada item checklist</div>
                        <div class="empty-state-desc">Tambahkan poin persiapan atau gunakan preset di bawah.</div>
                    </div>
                @endif
            </div>

            {{-- Add item form --}}
            <div class="section-footer">
                <form method="POST" action="{{ route('applications.checklists.store', $application) }}" style="display: flex; gap: 0.5rem;">
                    @csrf
                    <input type="text" name="title" class="input" placeholder="Item persiapan baru..." required maxlength="255" style="flex: 1;">
                    <button type="submit" class="btn btn-default btn-sm" style="white-space: nowrap;">Tambah</button>
                </form>
                <div style="margin-top: 0.75rem; display: flex; flex-wrap: wrap; gap: 0.375rem;">
                    <form method="POST" action="{{ route('applications.checklists.store', $application) }}" style="display: inline;">
                        @csrf
                        <input type="hidden" name="title" value="Riset Perusahaan">
                        <button type="submit" class="btn btn-outline btn-sm">+ Riset Perusahaan</button>
                    </form>
                    <form method="POST" action="{{ route('applications.checklists.store', $application) }}" style="display: inline;">
                        @csrf
                        <input type="hidden" name="title" value="Persiapan Behavioral (STAR)">
                        <button type="submit" class="btn btn-outline btn-sm">+ Persiapan Behavioral (STAR)</button>
                    </form>
                    <form method="POST" action="{{ route('applications.checklists.store', $application) }}" style="display: inline;">
                        @csrf
                        <input type="hidden" name="title" value="Review System Design / Coding">
                        <button type="submit" class="btn btn-outline btn-sm">+ Review System Design / Coding</button>
                    </form>
                </div>
            </div>
        </div>

        {{-- 4. Notes (if exists) --}}
        @if ($application->notes)
        <div class="section-card">
            <div class="section-header">
                <span class="section-title">Catatan Bebas</span>
            </div>
            <div class="section-body" style="padding: 1.25rem;">
                <pre class="notes-pre">{{ $application->notes }}</pre>
            </div>
        </div>
        @endif

        {{-- 4b. Hasil Interview (if exists) --}}
        @if ($application->interview_result)
        <div class="section-card" id="interview-result-card">
            <div class="section-header">
                <span class="section-title">Hasil Interview</span>
            </div>
            <div class="section-body" style="padding: 1.25rem;">
                <pre class="notes-pre">{{ $application->interview_result }}</pre>
            </div>
        </div>
        @endif

        {{-- 5. Offer details (if applicable) --}}
        @if (in_array($application->status, ['offer', 'hired']) || $application->offerDetail)
        @php $offer = $application->offerDetail; @endphp
        <div class="section-card" id="offer-details-card">
            <div class="section-header">
                <span class="section-title">Detail Penawaran Kerja (Offer Details)</span>
                <a href="{{ route('offers.index') }}" class="btn btn-ghost btn-sm">Komparasi &rarr;</a>
            </div>

            @if ($offer)
            <div class="offer-grid">
                <div>
                    <div class="info-label">Gaji Pokok</div>
                    <div class="info-value" style="font-weight: 600;">
                        @if ($offer->base_salary !== null)
                            Rp {{ number_format($offer->base_salary, 0, ',', '.') }}
                            <span class="text-xs text-muted font-normal">/ {{ match($offer->salary_period) { 'monthly' => 'bulan', 'yearly' => 'tahun', 'weekly' => 'minggu', 'hourly' => 'jam', default => $offer->salary_period ?: 'bulan' } }}</span>
                        @else —
                        @endif
                    </div>
                </div>
                <div>
                    <div class="info-label">Skema Kerja</div>
                    <div class="info-value">
                        @if ($offer->work_scheme)
                            <span class="badge badge-secondary">{{ $offer->work_scheme }}</span>
                        @else —
                        @endif
                    </div>
                </div>
                <div>
                    <div class="info-label">THR &amp; Bonus</div>
                    <div class="info-value">
                        @if ($offer->thr || $offer->bonus)
                            {{ $offer->thr ? 'THR: ' . $offer->thr : '' }}{{ $offer->thr && $offer->bonus ? ' · ' : '' }}{{ $offer->bonus ? 'Bonus: ' . $offer->bonus : '' }}
                        @else —
                        @endif
                    </div>
                </div>
                <div>
                    <div class="info-label">Tunjangan</div>
                    <div class="info-value">{{ $offer->allowance ?: '—' }}</div>
                </div>
                <div>
                    <div class="info-label">Asuransi Kesehatan</div>
                    <div class="info-value">{{ $offer->health_insurance ?: '—' }}</div>
                </div>
                <div>
                    <div class="info-label">Deadline Keputusan</div>
                    <div class="info-value">
                        @if ($offer->deadline_at)
                            @php $dl = $offer->deadline_at; $isOver = $dl->isPast() && !$dl->isToday(); @endphp
                            <span style="font-weight: 600; color: {{ $isOver ? 'hsl(var(--destructive))' : 'inherit' }};">{{ $dl->format('d M Y') }}</span>
                            <span class="text-xs {{ $isOver ? 'field-error' : 'text-muted' }}">({{ $isOver ? 'Lewat tenggat' : ($dl->isToday() ? 'Hari ini' : $dl->diffForHumans()) }})</span>
                        @else —
                        @endif
                    </div>
                </div>
                @if ($offer->notes)
                <div class="offer-grid-full">
                    <div class="info-label">Catatan Penawaran</div>
                    <div class="info-value"><pre class="notes-pre">{{ $offer->notes }}</pre></div>
                </div>
                @endif
            </div>
            @endif

            {{-- Offer edit/create form --}}
            <div class="offer-form-section">
                <div class="info-label" style="margin-bottom: 0.75rem;">{{ $offer ? 'Perbarui Rincian Penawaran Kerja' : 'Masukkan Rincian Penawaran Kerja' }}</div>
                <form method="POST" action="{{ route('offers.save', $application) }}">
                    @csrf
                    <div class="offer-form-grid">
                        <div>
                            <label for="offer_base_salary" class="label">Gaji Pokok (angka)</label>
                            <input type="number" id="offer_base_salary" name="base_salary" class="input @error('base_salary') input-error @enderror" placeholder="25000000" value="{{ old('base_salary', $offer?->base_salary) }}" min="0">
                            @error('base_salary')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div>
                            <label for="offer_salary_period" class="label">Periode Gaji</label>
                            <select id="offer_salary_period" name="salary_period" class="select @error('salary_period') select-error @enderror">
                                <option value="monthly" {{ old('salary_period', $offer?->salary_period ?? 'monthly') === 'monthly' ? 'selected' : '' }}>Bulanan</option>
                                <option value="yearly"  {{ old('salary_period', $offer?->salary_period) === 'yearly'  ? 'selected' : '' }}>Tahunan</option>
                                <option value="weekly"  {{ old('salary_period', $offer?->salary_period) === 'weekly'  ? 'selected' : '' }}>Mingguan</option>
                                <option value="hourly"  {{ old('salary_period', $offer?->salary_period) === 'hourly'  ? 'selected' : '' }}>Per Jam</option>
                            </select>
                            @error('salary_period')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div>
                            <label for="offer_thr" class="label">THR</label>
                            <input type="text" id="offer_thr" name="thr" class="input @error('thr') input-error @enderror" placeholder="1x Gaji Pokok" value="{{ old('thr', $offer?->thr) }}">
                            @error('thr')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div>
                            <label for="offer_bonus" class="label">Bonus</label>
                            <input type="text" id="offer_bonus" name="bonus" class="input @error('bonus') input-error @enderror" placeholder="1–3x gaji tahunan" value="{{ old('bonus', $offer?->bonus) }}">
                            @error('bonus')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div>
                            <label for="offer_allowance" class="label">Tunjangan</label>
                            <input type="text" id="offer_allowance" name="allowance" class="input @error('allowance') input-error @enderror" placeholder="Makan, transport..." value="{{ old('allowance', $offer?->allowance) }}">
                            @error('allowance')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div>
                            <label for="offer_health_insurance" class="label">Asuransi Kesehatan</label>
                            <input type="text" id="offer_health_insurance" name="health_insurance" class="input @error('health_insurance') input-error @enderror" placeholder="BPJS + Swasta" value="{{ old('health_insurance', $offer?->health_insurance) }}">
                            @error('health_insurance')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div>
                            <label for="offer_work_scheme" class="label">Skema Kerja</label>
                            <select id="offer_work_scheme" name="work_scheme" class="select @error('work_scheme') select-error @enderror">
                                <option value="">-- Pilih --</option>
                                <option value="Remote" {{ old('work_scheme', $offer?->work_scheme) === 'Remote'  ? 'selected' : '' }}>Remote</option>
                                <option value="Hybrid" {{ old('work_scheme', $offer?->work_scheme) === 'Hybrid'  ? 'selected' : '' }}>Hybrid</option>
                                <option value="Onsite" {{ old('work_scheme', $offer?->work_scheme) === 'Onsite'  ? 'selected' : '' }}>Onsite</option>
                            </select>
                            @error('work_scheme')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div>
                            <label for="offer_deadline_at" class="label">Deadline Keputusan</label>
                            <input type="date" id="offer_deadline_at" name="deadline_at" class="input @error('deadline_at') input-error @enderror" value="{{ old('deadline_at', $offer?->deadline_at?->format('Y-m-d')) }}">
                            @error('deadline_at')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label for="offer_notes" class="label">Catatan Khusus Penawaran</label>
                        <textarea id="offer_notes" name="notes" rows="3" class="textarea @error('notes') textarea-error @enderror" placeholder="Negosiasi, syarat cuti, probation, benefit lain...">{{ old('notes', $offer?->notes) }}</textarea>
                        @error('notes')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div style="display: flex; justify-content: flex-end;">
                        <button type="submit" class="btn btn-default btn-sm">{{ $offer ? 'Perbarui Penawaran' : 'Simpan Penawaran' }}</button>
                    </div>
                </form>
            </div>
        </div>
        @endif

        {{-- 6. Add note to history --}}
        <div class="section-card">
            <div class="section-header">
                <span class="section-title">Tambah Catatan / Progres Interview</span>
            </div>
            <div class="section-body" style="padding: 1.25rem;">
                <form method="POST" action="{{ route('applications.histories.store', $application) }}">
                    @csrf
                    <div style="margin-bottom: 0.875rem;">
                        <label for="history_note" class="label">Catatan Progres</label>
                        <textarea
                            id="history_note"
                            name="note"
                            rows="4"
                            class="textarea @if ($errors->has('note') && !old('status')) textarea-error @endif"
                            placeholder="Hasil teknikal tes, pertanyaan yang muncul, feedback recruiter..."
                            required
                        >{{ !old('status') ? old('note') : '' }}</textarea>
                        @if ($errors->has('note') && !old('status'))
                            <span class="field-error">{{ $errors->first('note') }}</span>
                        @endif
                    </div>
                    <button type="submit" class="btn btn-default w-full">Simpan Catatan</button>
                </form>
            </div>
        </div>

        {{-- 7. Actions: Edit + Delete --}}
        <div class="section-card">
            <div class="section-header">
                <span class="section-title">Tindakan</span>
            </div>
            <div class="section-body" style="padding: 1rem 1.25rem; display: flex; flex-direction: column; gap: 0.5rem;">
                <a href="{{ route('applications.edit', $application) }}" class="btn btn-outline w-full">Edit Lamaran</a>
                <form method="POST" action="{{ route('applications.destroy', $application) }}" onsubmit="return confirm('Hapus lamaran {{ addslashes($application->company) }} — {{ addslashes($application->position) }}? Seluruh riwayat status juga akan dihapus.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-destructive w-full">Hapus Lamaran</button>
                </form>
            </div>
        </div>

    </div>{{-- /right --}}

</div>{{-- /split-grid --}}
@endsection
