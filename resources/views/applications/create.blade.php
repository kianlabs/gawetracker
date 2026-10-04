@extends('layouts.app')

@section('title', 'Tambah Lamaran Kerja Baru')

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

    <div class="page-header">
        <div class="page-header-meta">
            <h1 class="page-title">Tambah Lamaran Kerja Baru</h1>
            <p class="page-subtitle">Daftarkan lowongan yang baru Anda lamar</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('applications.index') }}" class="btn btn-ghost btn-sm">Kembali</a>
        </div>
    </div>

    <div class="section-card">
        <div class="section-header">
            <span class="section-title">Detail Lamaran</span>
        </div>
        <div style="padding: 1.25rem;">
            <form method="POST" action="{{ route('applications.store') }}" novalidate>
                @csrf

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
                            placeholder="Contoh: Gojek, Traveloka, dsb."
                            value="{{ old('company') }}"
                            required
                            autofocus
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
                            placeholder="Contoh: Backend Engineer, Fullstack Dev"
                            value="{{ old('position') }}"
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
                            placeholder="Contoh: Jakarta Selatan, BSD, Remote"
                            value="{{ old('location') }}"
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
                                <option value="{{ $key }}" {{ old('work_type') === $key ? 'selected' : '' }}>
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
                            placeholder="Contoh: LinkedIn, Glints, JobStreet, Teman"
                            value="{{ old('source') }}"
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
                            placeholder="https://..."
                            value="{{ old('source_url') }}"
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
                            value="{{ old('applied_at', date('Y-m-d')) }}"
                            required
                        >
                        @error('applied_at')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Status -->
                    <div>
                        <label for="status" class="label">
                            Status Awal <span style="color: hsl(var(--destructive));">*</span>
                        </label>
                        <select
                            id="status"
                            name="status"
                            class="select @error('status') select-error @enderror"
                            required
                        >
                            @foreach ($statuses as $key => $meta)
                                <option value="{{ $key }}" {{ old('status', 'wishlist') === $key ? 'selected' : '' }}>
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
                            placeholder="Contoh: Rp 20jt - 25jt, Negosiasi"
                            value="{{ old('salary_note') }}"
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
                            placeholder="Contoh: Sarah / Budi"
                            value="{{ old('contact_name') }}"
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
                            placeholder="Contoh: hr@company.com / 08123456789"
                            value="{{ old('contact_info') }}"
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
                            placeholder="Informasi tambahan, requirements teknis penting, atau persiapan interview..."
                        >{{ old('notes') }}</textarea>
                        @error('notes')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Hasil Interview -->
                    <div class="md:col-span-2">
                        <label for="interview_result" class="label">
                            Hasil Interview
                            <span style="font-weight: normal; color: hsl(var(--muted-foreground)); font-size: 0.8125rem;">(opsional)</span>
                        </label>
                        <textarea
                            id="interview_result"
                            name="interview_result"
                            rows="3"
                            class="textarea @error('interview_result') textarea-error @enderror"
                            placeholder="Hasil/kesan tiap tahap wawancara — pertanyaan yang muncul, feedback pewawancara, hal yang perlu diperbaiki..."
                        >{{ old('interview_result') }}</textarea>
                        @error('interview_result')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="flex items-center justify-between mt-6" style="border-top: 1px solid hsl(var(--border)); padding-top: 1.25rem;">
                    <a href="{{ route('applications.index') }}" class="btn btn-ghost">Batal</a>
                    <button type="submit" class="btn btn-default">Simpan Lamaran</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
