@extends('layouts.app')

@section('title', 'Edit Lamaran: ' . $application->company)

@section('styles')
<style>
    .form-col-wrapper {
        max-width: 48rem;
        margin: 0 auto;
    }
    @media (min-width: 768px) {
        .md\:col-span-2 {
            grid-column: span 2 / span 2;
        }
    }
</style>
@endsection

@section('content')
<div class="form-col-wrapper">

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
        $currentStatusLabel = ($statuses[$application->status] ?? ['label' => $application->status])['label'];
    @endphp

    <div class="page-header">
        <div class="page-header-meta">
            <h1 class="page-title">Edit Lamaran Kerja</h1>
            <p class="page-subtitle">{{ $application->company }} &mdash; {{ $application->position }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('applications.show', $application) }}" class="btn btn-ghost btn-sm">Kembali</a>
        </div>
    </div>

    <div class="section-card">
        <div class="section-header">
            <span class="section-title">Detail Lamaran</span>
            <span class="badge {{ $currentBadge }}">{{ $currentStatusLabel }}</span>
        </div>
        <div style="padding: 1.25rem;">
            <form method="POST" action="{{ route('applications.update', $application) }}" novalidate>
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Perusahaan -->
                    <div>
                        <label for="company" class="label">
                            Nama Perusahaan <span style="color: hsl(var(--destructive));">*</span>
                        </label>
                        <input
                            type="text"
                            id="company"
                            name="company"
                            class="input @error('company') input-error @enderror"
                            value="{{ old('company', $application->company) }}"
                            required
                        >
                        @error('company')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Posisi -->
                    <div>
                        <label for="position" class="label">
                            Posisi Pekerjaan <span style="color: hsl(var(--destructive));">*</span>
                        </label>
                        <input
                            type="text"
                            id="position"
                            name="position"
                            class="input @error('position') input-error @enderror"
                            value="{{ old('position', $application->position) }}"
                            required
                        >
                        @error('position')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Lokasi -->
                    <div>
                        <label for="location" class="label">Lokasi</label>
                        <input
                            type="text"
                            id="location"
                            name="location"
                            class="input @error('location') input-error @enderror"
                            value="{{ old('location', $application->location) }}"
                        >
                        @error('location')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Tipe Kerja -->
                    <div>
                        <label for="work_type" class="label">Tipe Kerja</label>
                        <select
                            id="work_type"
                            name="work_type"
                            class="select @error('work_type') select-error @enderror"
                        >
                            <option value="">Pilih Tipe Kerja</option>
                            @foreach ($workTypes as $key => $label)
                                <option value="{{ $key }}" {{ old('work_type', $application->work_type) === $key ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('work_type')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Sumber -->
                    <div>
                        <label for="source" class="label">Sumber Lowongan</label>
                        <input
                            type="text"
                            id="source"
                            name="source"
                            class="input @error('source') input-error @enderror"
                            value="{{ old('source', $application->source) }}"
                        >
                        @error('source')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Link Lowongan -->
                    <div>
                        <label for="source_url" class="label">Tautan Lowongan (URL)</label>
                        <input
                            type="url"
                            id="source_url"
                            name="source_url"
                            class="input @error('source_url') input-error @enderror"
                            value="{{ old('source_url', $application->source_url) }}"
                        >
                        @error('source_url')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Tanggal Melamar -->
                    <div>
                        <label for="applied_at" class="label">
                            Tanggal Melamar <span style="color: hsl(var(--destructive));">*</span>
                        </label>
                        <input
                            type="date"
                            id="applied_at"
                            name="applied_at"
                            class="input @error('applied_at') input-error @enderror"
                            value="{{ old('applied_at', $application->applied_at ? $application->applied_at->format('Y-m-d') : '') }}"
                            required
                        >
                        @error('applied_at')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Status -->
                    <div>
                        <label for="status" class="label">
                            Tahap Status <span style="color: hsl(var(--destructive));">*</span>
                        </label>
                        <select
                            id="status"
                            name="status"
                            class="select @error('status') select-error @enderror"
                            required
                        >
                            @foreach ($statuses as $key => $meta)
                                <option value="{{ $key }}" {{ old('status', $application->status) === $key ? 'selected' : '' }}>
                                    {{ $meta['label'] }}
                                </option>
                            @endforeach
                        </select>
                        @error('status')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Catatan Gaji -->
                    <div>
                        <label for="salary_note" class="label">Catatan Gaji / Kompensasi</label>
                        <input
                            type="text"
                            id="salary_note"
                            name="salary_note"
                            class="input @error('salary_note') input-error @enderror"
                            value="{{ old('salary_note', $application->salary_note) }}"
                        >
                        @error('salary_note')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Nama Kontak HR -->
                    <div>
                        <label for="contact_name" class="label">Nama Kontak HR / Recruiter</label>
                        <input
                            type="text"
                            id="contact_name"
                            name="contact_name"
                            class="input @error('contact_name') input-error @enderror"
                            value="{{ old('contact_name', $application->contact_name) }}"
                        >
                        @error('contact_name')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Info Kontak HR -->
                    <div class="md:col-span-2">
                        <label for="contact_info" class="label">Info Kontak HR (Email / WhatsApp / LinkedIn)</label>
                        <input
                            type="text"
                            id="contact_info"
                            name="contact_info"
                            class="input @error('contact_info') input-error @enderror"
                            value="{{ old('contact_info', $application->contact_info) }}"
                        >
                        @error('contact_info')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Catatan Bebas -->
                    <div class="md:col-span-2">
                        <label for="notes" class="label">Catatan Bebas</label>
                        <textarea
                            id="notes"
                            name="notes"
                            rows="4"
                            class="textarea @error('notes') textarea-error @enderror"
                        >{{ old('notes', $application->notes) }}</textarea>
                        @error('notes')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Catatan Perubahan Status -->
                    <div class="md:col-span-2" style="border-top: 1px solid hsl(var(--border)); padding-top: 1.25rem; margin-top: 0.25rem;">
                        <label for="status_change_note" class="label">
                            Catatan Perubahan Status
                            <span style="font-weight: normal; color: hsl(var(--muted-foreground)); font-size: 0.8125rem;">(opsional)</span>
                        </label>
                        <p style="font-size: 0.8125rem; color: hsl(var(--muted-foreground)); margin-bottom: 0.5rem; margin-top: 0.25rem;">
                            Catatan ini akan tercatat di riwayat saat status berubah.
                        </p>
                        <input
                            type="text"
                            id="status_change_note"
                            name="status_change_note"
                            class="input"
                            placeholder="Contoh: Lolos screening HR, dijadwalkan wawancara user minggu depan."
                            value="{{ old('status_change_note') }}"
                        >
                    </div>
                </div>

                <div class="flex items-center justify-between mt-6" style="border-top: 1px solid hsl(var(--border)); padding-top: 1.25rem;">
                    <a href="{{ route('applications.show', $application) }}" class="btn btn-ghost">Batal</a>
                    <button type="submit" class="btn btn-default">Perbarui Lamaran</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
