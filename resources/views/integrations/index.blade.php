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
        background-color: hsl(240 5.9% 10%);
        color: #f8fafc;
        padding: 1rem;
        border-radius: var(--radius);
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 0.8125rem;
        line-height: 1.5;
        overflow-x: auto;
        position: relative;
    }
    .token-mask {
        font-family: ui-monospace, monospace;
        letter-spacing: 0.1em;
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
            <span class="badge badge-purple" style="font-size:0.75rem;">🤖 API &amp; Automation</span>
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
                    <label class="info-label" for="webhook-url">Webhook Ingestion URL (POST)</label>
                    <div style="display:flex;gap:0.5rem;align-items:center;">
                        <input type="text"
                               id="webhook-url"
                               readonly
                               value="{{ $webhookUrl }}"
                               class="input"
                               style="font-family:ui-monospace,monospace;font-size:0.8125rem;background-color:hsl(var(--muted)/0.3);flex:1;">
                        <button type="button"
                                class="btn btn-secondary btn-sm"
                                onclick="copyText('webhook-url', this)">
                            Salin URL
                        </button>
                    </div>
                    <p class="text-xs text-muted" style="margin-top:0.25rem;">Gunakan URL ini sebagai target output webhook di bot atau extension.</p>
                </div>

                {{-- API Token --}}
                <div>
                    <label class="info-label" for="api-token">Personal API Key / Secret Token</label>
                    <div style="display:flex;gap:0.5rem;align-items:center;">
                        <input type="password"
                               id="api-token"
                               readonly
                               value="{{ $apiToken }}"
                               class="input token-mask"
                               style="font-size:0.8125rem;background-color:hsl(var(--muted)/0.3);flex:1;">
                        <button type="button"
                                class="btn btn-outline btn-sm"
                                onclick="togglePasswordVisibility('api-token', this)">
                            Tampilkan
                        </button>
                        <button type="button"
                                class="btn btn-secondary btn-sm"
                                onclick="copyText('api-token', this)">
                            Salin Key
                        </button>
                    </div>
                    <p class="text-xs text-muted" style="margin-top:0.25rem;">
                        Sertakan token ini dalam header <code>X-API-Key: {token}</code> atau <code>Authorization: Bearer {token}</code>.
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
                <span class="badge badge-purple">{{ $recentBotApps->count() }} Terakhir</span>
            </div>
            <div class="section-body">
                @if ($recentBotApps->isEmpty())
                    <div style="padding:2.5rem 1rem;text-align:center;">
                        <span style="font-size:2rem;display:block;margin-bottom:0.5rem;">🤖</span>
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
                                            <div class="text-xs" style="color:hsl(262 83% 55%);margin-top:0.125rem;">
                                                🤖 {{ $app->source }}
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
                    <span style="font-size:1.125rem;">⚡</span>
                    <div>
                        <strong class="text-sm text-foreground">Auto-Card di Kanban Board</strong>
                        <p class="text-xs text-muted">Setiap lamaran yang ditembak bot langsung bertengger di kolom <code>Terkirim (Applied)</code>.</p>
                    </div>
                </div>

                <div style="display:flex;gap:0.75rem;align-items:flex-start;">
                    <span style="font-size:1.125rem;">🛡️</span>
                    <div>
                        <strong class="text-sm text-foreground">Pencegah Lamaran Dobel (Deduplication)</strong>
                        <p class="text-xs text-muted">Jika bot mengirim perusahaan dan posisi yang sama dalam 30 hari terakhir, sistem otomatis menandainya agar database tetap bersih.</p>
                    </div>
                </div>

                <div style="display:flex;gap:0.75rem;align-items:flex-start;">
                    <span style="font-size:1.125rem;">🎯</span>
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
</script>
@endsection
