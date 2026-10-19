@extends('layouts.app')

@section('title', 'Integrasi Bot & API')

@section('styles')
<style>
    .integration-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.25rem;
    }
    @media (min-width: 1024px) {
        .integration-grid {
            grid-template-columns: 7fr 5fr;
        }
    }
    .code-block {
        background-color: #18181b;
        color: #fafafa;
        padding: 1rem;
        border-radius: var(--radius);
        border: 1px solid hsl(var(--border));
        font-family: var(--font-mono);
        font-size: 0.8125rem;
        line-height: 1.5;
        overflow-x: auto;
        position: relative;
    }
    .token-mask {
        font-family: var(--font-mono);
        letter-spacing: 0.08em;
    }
    .endpoint-box {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        background-color: hsl(var(--card));
        border: 1px solid hsl(var(--border));
        border-radius: var(--radius);
        padding: 0.375rem 0.5rem 0.375rem 0.75rem;
        box-shadow: var(--shadow-micro);
    }
    .endpoint-input {
        font-family: var(--font-mono);
        font-size: 0.8125rem;
        color: hsl(var(--foreground));
        background: transparent;
        border: none;
        outline: none;
        width: 100%;
        flex: 1;
    }
    .metric-badge-box {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.75rem;
        margin-bottom: 1.25rem;
    }
    @media (max-width: 640px) {
        .metric-badge-box {
            grid-template-columns: 1fr;
        }
    }
</style>
@endsection

@section('content')
<div class="page-header">
    <div class="page-header-meta">
        <div style="display:flex;align-items:center;gap:0.5rem;">
            <span class="badge badge-purple" style="font-size:0.75rem;">API &amp; Automation</span>
            <h1 class="page-title">Integrasi Bot &amp; Webhook</h1>
        </div>
        <p class="page-subtitle">Hubungkan bot otomatis seperti Bot Lempar Jaring Loker atau Chrome Extension langsung ke pipeline GaweTracker</p>
    </div>
    <div class="page-actions">
        <a href="{{ route('applications.kanban') }}" class="btn btn-outline">Lihat Kanban</a>
        <a href="{{ route('dashboard') }}" class="btn btn-ghost">Dashboard</a>
    </div>
</div>

{{-- Top KPI Cards --}}
<div class="metric-badge-box">
    <div class="section-card" style="padding:1rem;">
        <span class="text-xs text-muted uppercase tracking-wider font-semibold">Status API Webhook</span>
        <div style="display:flex;align-items:center;gap:0.5rem;margin-top:0.375rem;">
            <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background-color:hsl(142 76% 36%);"></span>
            <span class="font-bold text-foreground">Siap Menerima Data</span>
        </div>
        <span class="text-xs text-muted" style="margin-top:0.25rem;display:block;">Token terotentikasi &amp; aktif</span>
    </div>

    <div class="section-card" style="padding:1rem;">
        <span class="text-xs text-muted uppercase tracking-wider font-semibold">Total Masuk via Bot</span>
        <div style="font-size:1.5rem;font-weight:700;margin-top:0.25rem;color:hsl(var(--foreground));" class="tabular-nums">
            {{ $totalBot }} <span class="text-xs font-normal text-muted">lamaran</span>
        </div>
        <span class="text-xs text-muted" style="display:block;">Tercatat otomatis di pipeline</span>
    </div>

    <div class="section-card" style="padding:1rem;">
        <span class="text-xs text-muted uppercase tracking-wider font-semibold">Pekan Ini</span>
        <div style="font-size:1.5rem;font-weight:700;margin-top:0.25rem;color:hsl(217 91% 40%);" class="tabular-nums">
            {{ $botThisWeek }} <span class="text-xs font-normal text-muted">lamaran baru</span>
        </div>
        <span class="text-xs text-muted" style="display:block;">Periode minggu berjalan</span>
    </div>
</div>

{{-- ── Auto-Hunter Engine Command Center ─────────────────────────────── --}}
<div class="section-card" style="margin-bottom:1.5rem;">
    <div class="section-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.75rem;">
        <div style="display:flex;align-items:center;gap:0.625rem;">
            <span class="brand-logo-badge" style="width:1.625rem;height:1.625rem;font-size:0.8125rem;">G</span>
            <div>
                <span class="section-title" style="font-size:0.9375rem;">GaweTracker Auto-Hunter Engine</span>
                <span class="text-xs text-muted" style="display:block;">Pusat kendali robot pelamar otomatis multi-portal</span>
            </div>
        </div>
        <div style="display:flex;gap:0.5rem;align-items:center;">
            @if ($botEngineOnline)
                <span class="badge badge-success" style="font-size:0.6875rem;">● Engine Online (Port 3000)</span>
            @else
                <span class="badge badge-warning" style="font-size:0.6875rem;">Engine Standby</span>
            @endif
            <a href="http://localhost:3000" target="_blank" class="btn btn-outline btn-sm" style="height:1.75rem;padding:0 0.5rem;font-size:0.75rem;display:inline-flex;align-items:center;gap:0.25rem;">
                <span>Full Engine Studio</span>
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
            </a>
        </div>
    </div>

    <div class="section-body" style="padding:1.25rem;">
        {{-- Control buttons & Live status --}}
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;padding-bottom:1rem;border-bottom:1px solid hsl(var(--border));margin-bottom:1.25rem;">
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;">
                <button type="button" id="btnRunHeadless" onclick="startBotExecution('headless')" class="btn btn-default btn-sm" style="display:inline-flex;align-items:center;gap:0.375rem;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    <span>Jalankan Auto-Apply (Headless)</span>
                </button>
                <button type="button" id="btnRunSim" onclick="startBotExecution('simulation')" class="btn btn-outline btn-sm" style="display:inline-flex;align-items:center;gap:0.375rem;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <span>Uji Mode Simulasi (Dry Run)</span>
                </button>
                <button type="button" id="btnStopBot" onclick="stopBotExecution()" class="btn btn-destructive btn-sm" style="display:none;align-items:center;gap:0.375rem;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="6" width="12" height="12"/></svg>
                    <span>Hentikan Bot</span>
                </button>
            </div>
            <div style="display:flex;align-items:center;gap:0.5rem;">
                <span id="botExecStatus" class="text-xs text-muted font-medium">Status: Standby</span>
                <button type="button" onclick="clearBotLogs()" class="btn btn-ghost btn-sm" style="height:1.625rem;font-size:0.6875rem;padding:0 0.375rem;">Bersihkan Log</button>
            </div>
        </div>

        {{-- Grid: Target Config Form (Left) & Real-time Terminal Log (Right) --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:1.25rem;">
            {{-- Target Criteria Form --}}
            <form method="POST" action="{{ route('integrations.bot.config') }}" style="display:flex;flex-direction:column;gap:0.875rem;">
                @csrf
                <div>
                    <label class="info-label" for="searchKeywords">Kata Kunci Posisi Target</label>
                    <input type="text" id="searchKeywords" name="searchKeywords" class="input" style="margin-top:0.25rem;"
                           value="{{ old('searchKeywords', $botConfig['searchKeywords'] ?? 'Frontend Developer') }}" required>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
                    <div>
                        <label class="info-label" for="location">Lokasi Target</label>
                        <input type="text" id="location" name="location" class="input" style="margin-top:0.25rem;"
                               value="{{ old('location', $botConfig['location'] ?? 'Jakarta') }}">
                    </div>
                    <div>
                        <label class="info-label" for="minSalary">Gaji Minimal (IDR)</label>
                        <input type="text" id="minSalary" name="minSalary" class="input tabular-nums" style="margin-top:0.25rem;"
                               value="{{ old('minSalary', $botConfig['minSalary'] ?? '5000000') }}">
                    </div>
                </div>

                <div>
                    <label class="info-label" style="margin-bottom:0.375rem;display:block;">Portal Aktif &amp; Batas Harian</label>
                    <div style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:center;">
                        <label style="display:flex;align-items:center;gap:0.375rem;font-size:0.75rem;cursor:pointer;">
                            <input type="checkbox" name="enableGlints" value="1" {{ !empty($botConfig['enableGlints']) ? 'checked' : '' }}>
                            <span>Glints</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:0.375rem;font-size:0.75rem;cursor:pointer;">
                            <input type="checkbox" name="enableJobstreet" value="1" {{ !empty($botConfig['enableJobstreet']) ? 'checked' : '' }}>
                            <span>JobStreet</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:0.375rem;font-size:0.75rem;cursor:pointer;">
                            <input type="checkbox" name="enableIndeed" value="1" {{ !empty($botConfig['enableIndeed']) ? 'checked' : '' }}>
                            <span>Indeed</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:0.375rem;font-size:0.75rem;cursor:pointer;">
                            <input type="checkbox" name="enableLinkedin" value="1" {{ !empty($botConfig['enableLinkedin']) ? 'checked' : '' }}>
                            <span>LinkedIn</span>
                        </label>
                    </div>
                </div>

                <div style="display:flex;align-items:center;justify-content:space-between;margin-top:0.25rem;">
                    <div style="display:flex;align-items:center;gap:0.5rem;">
                        <label for="limitPerDay" class="text-xs text-muted">Kuota Maks / Hari:</label>
                        <input type="number" id="limitPerDay" name="limitPerDay" class="input tabular-nums" style="width:4.5rem;height:1.75rem;padding:0 0.5rem;font-size:0.75rem;"
                               value="{{ old('limitPerDay', $botConfig['limitPerDay'] ?? 20) }}" min="1" max="100">
                    </div>
                    <button type="submit" class="btn btn-secondary btn-sm" style="height:1.75rem;font-size:0.75rem;">Simpan Kriteria</button>
                </div>
            </form>

            {{-- Live Terminal Console --}}
            <div style="display:flex;flex-direction:column;gap:0.375rem;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span class="info-label">Live Execution Terminal</span>
                    <span id="logCounter" class="text-xs text-muted tabular-nums">0 baris</span>
                </div>
                <div id="botTerminalLogs"
                     style="height:210px;background:#09090b;color:#a1a1aa;border:1px solid hsl(var(--border));border-radius:var(--radius);padding:0.75rem;font-family:var(--font-mono);font-size:0.6875rem;line-height:1.45;overflow-y:auto;white-space:pre-wrap;word-break:break-word;">
[SISTEM] GaweTracker Auto-Hunter Engine siap.
[INFO] Klik "Jalankan Auto-Apply" atau "Uji Mode Simulasi" untuk memulai sesi automasi.
                </div>
            </div>
        </div>
    </div>
</div>

<div class="integration-grid">
    {{-- Left: Credentials & Documentation --}}
    <div style="display:flex;flex-direction:column;gap:1.25rem;">
        {{-- 1. Webhook Endpoint & API Key Card --}}
        <div class="section-card">
            <div class="section-header">
                <span class="section-title">Kredensial API &amp; Webhook</span>
                <span class="badge badge-secondary">Private</span>
            </div>
            <div class="section-body" style="padding:1.25rem;display:flex;flex-direction:column;gap:1rem;">
                {{-- Webhook URL --}}
                <div>
                    <label class="info-label" for="webhook-url">Webhook Ingestion URL</label>
                    <div class="endpoint-box" style="margin-top:0.375rem;">
                        <span class="badge badge-secondary" style="font-size:0.6875rem;font-weight:700;letter-spacing:0.04em;">POST</span>
                        <input type="text"
                               id="webhook-url"
                               readonly
                               value="{{ $webhookUrl }}"
                               class="endpoint-input">
                        <button type="button"
                                class="btn btn-secondary btn-sm"
                                style="flex-shrink:0;"
                                onclick="copyText('webhook-url', this)">
                            Salin URL
                        </button>
                    </div>
                    <p class="text-xs text-muted" style="margin-top:0.375rem;">Gunakan URL ini sebagai target output webhook di bot atau extension.</p>
                </div>

                {{-- API Token --}}
                <div>
                    <label class="info-label" for="api-token">Personal API Key / Secret Token</label>
                    <div class="endpoint-box" style="margin-top:0.375rem;">
                        <input type="password"
                               id="api-token"
                               readonly
                               value="{{ $apiToken }}"
                               class="endpoint-input token-mask">
                        <button type="button"
                                class="btn btn-outline btn-sm"
                                style="flex-shrink:0;"
                                onclick="togglePasswordVisibility('api-token', this)">
                            Tampilkan
                        </button>
                        <button type="button"
                                class="btn btn-secondary btn-sm"
                                style="flex-shrink:0;"
                                onclick="copyText('api-token', this)">
                            Salin Key
                        </button>
                    </div>
                    <p class="text-xs text-muted" style="margin-top:0.375rem;">
                        Sertakan token ini dalam header <code style="font-family:var(--font-mono);font-size:0.75rem;">X-API-Key: {token}</code> atau <code style="font-family:var(--font-mono);font-size:0.75rem;">Authorization: Bearer {token}</code>.
                    </p>
                </div>

                {{-- Regenerate Form --}}
                <div style="padding-top:0.75rem;border-top:1px solid hsl(var(--border));display:flex;justify-content:flex-end;">
                    <form method="POST" action="{{ route('integrations.token.regenerate') }}" onsubmit="return confirm('Regenerate token akan membatalkan token lama. Lanjutkan?');">
                        @csrf
                        <button type="submit" class="btn btn-destructive btn-sm">
                            Regenerate API Token
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- 2. Panduan Integrasi Bot Lempar Jaring --}}
        <div class="section-card">
            <div class="section-header">
                <span class="section-title">Contoh Konfigurasi &amp; Penggunaan</span>
            </div>
            <div class="section-body" style="padding:1.25rem;display:flex;flex-direction:column;gap:1.25rem;">
                <div>
                    <h3 class="text-sm font-semibold mb-1" style="color:hsl(var(--foreground));">1. Format JSON Payload (Fleksibel &amp; Kompatibel)</h3>
                    <p class="text-xs text-muted mb-2">Endpoint ini otomatis memahami key dari <code>Bot-Lempar-Jaring-Loker</code> (title, company, platform, url, salary) maupun format standar GaweTracker.</p>
                    <div class="code-block">{
  "company": "PT GoTo Gojek Tokopedia",
  "position": "Senior Backend Engineer",
  "platform": "Glints",
  "url": "https://glints.com/id/opportunities/jobs/sample",
  "location": "Jakarta (Hybrid)",
  "work_type": "hybrid",
  "salary": "Rp 18.000.000 - Rp 25.000.000",
  "status": "applied",
  "notes": "Pertanyaan kuesioner otomatis dijawab via AI.\nNotice period: 1 bulan."
}</div>
                </div>

                <div>
                    <h3 class="text-sm font-semibold mb-1" style="color:hsl(var(--foreground));">2. Test Kirim via cURL (Terminal)</h3>
                    <p class="text-xs text-muted mb-2">Salin perintah berikut untuk menguji koneksi langsung dari terminal:</p>
                    <div class="code-block" id="curl-sample">curl -X POST "{{ $webhookUrl }}" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: {{ $apiToken }}" \
  -d '{
    "company": "Contoh Startup Bot",
    "position": "Fullstack Developer",
    "platform": "JobStreet",
    "url": "https://jobstreet.co.id/job/123",
    "salary": "Rp 15.000.000",
    "status": "applied",
    "notes": "Di-apply otomatis oleh Bot Lempar Jaring"
  }'</div>
                    <button type="button" class="btn btn-secondary btn-sm mt-2" onclick="copySnippet('curl-sample', this)">
                        Salin cURL Script
                    </button>
                </div>

                <div>
                    <h3 class="text-sm font-semibold mb-1" style="color:hsl(var(--foreground));">3. Skrip Hook Otomatis di Node.js / Bot Lempar Jaring</h3>
                    <p class="text-xs text-muted mb-2">Tambahkan fungsi ini pada file bot saat lamaran berhasil di-submit:</p>
                    <div class="code-block">// Panggil fungsi ini setelah bot selesai submit lamaran
async function pushToGaweTracker(jobData) {
  try {
    const res = await fetch('{{ $webhookUrl }}', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-API-Key': '{{ $apiToken }}',
      },
      body: JSON.stringify({
        company: jobData.company,
        position: jobData.title,
        platform: jobData.platform || 'Lempar Jaring',
        url: jobData.url,
        location: jobData.location,
        salary: jobData.salary,
        status: 'applied',
        notes: 'Otomatis di-apply oleh bot: ' + (jobData.notes || '')
      })
    });
    const result = await res.json();
    console.log('[GaweTracker Sync]', result.message);
  } catch (err) {
    console.error('[GaweTracker Error]', err);
  }
}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Right: Recent Bot Applications & Features --}}
    <div style="display:flex;flex-direction:column;gap:1.25rem;">
        {{-- Recent Bot Submissions --}}
        <div class="section-card">
            <div class="section-header">
                <span class="section-title">Lamaran Terbaru via Bot</span>
                <span class="badge badge-purple tabular-nums">{{ $recentBotApps->count() }} Terakhir</span>
            </div>
            <div class="section-body">
                @if ($recentBotApps->isEmpty())
                    <div style="padding:2.5rem 1rem;text-align:center;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:hsl(var(--muted-foreground));margin:0 auto 0.75rem auto;display:block;"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="m10 10-2 2 2 2"/><path d="m14 14 2-2-2-2"/></svg>
                        <p class="font-medium text-sm text-foreground">Belum ada lamaran via bot</p>
                        <p class="text-xs text-muted" style="margin-top:0.25rem;">Jalankan cURL di sebelah kiri atau hubungkan bot Anda untuk mulai mengisi otomatis.</p>
                    </div>
                @else
                    <div class="table-wrapper">
                        <table class="table" style="font-size:0.8125rem;">
                            <thead>
                                <tr>
                                    <th>Perusahaan &amp; Posisi</th>
                                    <th>Status</th>
                                    <th>Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentBotApps as $app)
                                    <tr>
                                        <td>
                                            <a href="{{ route('applications.show', $app) }}" class="font-semibold text-foreground" style="text-decoration:none;">
                                                {{ $app->company }}
                                            </a>
                                            <div class="text-xs text-muted">{{ $app->position }}</div>
                                            <div class="text-xs text-muted" style="margin-top:0.25rem;">
                                                <span class="badge badge-secondary" style="font-size:0.6875rem;">{{ $app->source }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-info" style="font-size:0.6875rem;">
                                                {{ ucfirst($app->status) }}
                                            </span>
                                        </td>
                                        <td class="tabular-nums text-muted text-xs">
                                            {{ $app->applied_at ? $app->applied_at->format('d M') : '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Keuntungan Fitur Integrasi --}}
        <div class="section-card">
            <div class="section-header">
                <span class="section-title">Fitur Otomatisasi Terintegrasi</span>
            </div>
            <div class="section-body" style="padding:1.25rem;display:flex;flex-direction:column;gap:0.75rem;">
                <div style="display:flex;gap:0.75rem;align-items:flex-start;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:hsl(var(--primary));flex-shrink:0;margin-top:2px;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                    <div>
                        <strong class="text-sm text-foreground">Auto-Card di Kanban Board</strong>
                        <p class="text-xs text-muted">Setiap lamaran yang ditembak bot langsung bertengger di kolom <code>Terkirim (Applied)</code>.</p>
                    </div>
                </div>

                <div style="display:flex;gap:0.75rem;align-items:flex-start;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:hsl(var(--primary));flex-shrink:0;margin-top:2px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <div>
                        <strong class="text-sm text-foreground">Pencegah Lamaran Dobel (Deduplication)</strong>
                        <p class="text-xs text-muted">Jika bot mengirim perusahaan dan posisi yang sama dalam 30 hari terakhir, sistem otomatis menandainya agar database tetap bersih.</p>
                    </div>
                </div>

                <div style="display:flex;gap:0.75rem;align-items:flex-start;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:hsl(var(--primary));flex-shrink:0;margin-top:2px;"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
                    <div>
                        <strong class="text-sm text-foreground">Sinkron ke Target Mingguan</strong>
                        <p class="text-xs text-muted">Semua lamaran bot dihitung ke kemajuan target 5–10 lamaran mingguan Anda.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function copyText(elementId, btn) {
        const input = document.getElementById(elementId);
        if (!input) return;
        navigator.clipboard.writeText(input.value).then(() => {
            const originalText = btn.textContent;
            btn.textContent = '✓ Tersalin!';
            btn.classList.add('btn-default');
            setTimeout(() => {
                btn.textContent = originalText;
                btn.classList.remove('btn-default');
            }, 2000);
        });
    }

    function copySnippet(snippetId, btn) {
        const snippet = document.getElementById(snippetId);
        if (!snippet) return;
        navigator.clipboard.writeText(snippet.textContent).then(() => {
            const originalText = btn.textContent;
            btn.textContent = '✓ Tersalin!';
            setTimeout(() => {
                btn.textContent = originalText;
            }, 2000);
        });
    }

    function togglePasswordVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
        if (!input) return;
        if (input.type === 'password') {
            input.type = 'text';
            btn.textContent = 'Sembunyikan';
        } else {
            input.type = 'password';
            btn.textContent = 'Tampilkan';
        }
    }

    let botEventSource = null;
    let lineCount = 2;

    function startBotExecution(mode) {
        const statusEl = document.getElementById('botExecStatus');
        const btnHeadless = document.getElementById('btnRunHeadless');
        const btnSim = document.getElementById('btnRunSim');
        const btnStop = document.getElementById('btnStopBot');

        if (botEventSource) {
            botEventSource.close();
        }

        statusEl.innerHTML = '<span style="color:hsl(142 76% 36%);font-weight:600;">● Berjalan (' + mode + ')...</span>';
        btnHeadless.disabled = true;
        btnSim.disabled = true;
        btnStop.style.display = 'inline-flex';

        appendTerminalLog('[MULAI] Memulai eksekusi bot mode ' + mode + '...');

        try {
            botEventSource = new EventSource('http://localhost:3000/api/run-bot?mode=' + mode);

            botEventSource.onmessage = function(e) {
                try {
                    const data = JSON.parse(e.data);
                    if (data.message) {
                        appendTerminalLog(data.message);
                    }
                } catch {
                    appendTerminalLog(e.data);
                }
            };

            botEventSource.onerror = function() {
                appendTerminalLog('[SELESAI] Sesi automasi bot selesai / standby.');
                statusEl.textContent = 'Status: Standby';
                btnHeadless.disabled = false;
                btnSim.disabled = false;
                btnStop.style.display = 'none';
                if (botEventSource) {
                    botEventSource.close();
                    botEventSource = null;
                }
            };
        } catch (err) {
            appendTerminalLog('[ERROR] Gagal menghubungi bot: ' + err.message);
            statusEl.textContent = 'Status: Error';
            btnHeadless.disabled = false;
            btnSim.disabled = false;
            btnStop.style.display = 'none';
        }
    }

    function stopBotExecution() {
        appendTerminalLog('[SISTEM] Mengirim sinyal penghentian ke Bot Engine...');
        fetch('http://localhost:3000/api/run-bot', { method: 'POST' })
            .then(res => res.json())
            .then(data => {
                appendTerminalLog('[SISTEM] ' + (data.message || 'Bot dihentikan.'));
                if (botEventSource) {
                    botEventSource.close();
                    botEventSource = null;
                }
                document.getElementById('botExecStatus').textContent = 'Status: Dihentikan';
                document.getElementById('btnRunHeadless').disabled = false;
                document.getElementById('btnRunSim').disabled = false;
                document.getElementById('btnStopBot').style.display = 'none';
            })
            .catch(err => {
                appendTerminalLog('[ERROR] Gagal menghentikan bot: ' + err.message);
            });
    }

    function appendTerminalLog(msg) {
        const terminal = document.getElementById('botTerminalLogs');
        const time = new Date().toLocaleTimeString('id-ID');
        terminal.textContent += '\n[' + time + '] ' + msg;
        terminal.scrollTop = terminal.scrollHeight;
        lineCount++;
        const counter = document.getElementById('logCounter');
        if (counter) counter.textContent = lineCount + ' baris';
    }

    function clearBotLogs() {
        const terminal = document.getElementById('botTerminalLogs');
        terminal.textContent = '[SISTEM] Log terminal dibersihkan.';
        lineCount = 1;
        const counter = document.getElementById('logCounter');
        if (counter) counter.textContent = '1 baris';
    }
</script>
@endsection
