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
                                    @php $initial = mb_strtoupper(mb_substr(trim($app->company) ?: '?', 0, 1)); @endphp
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
                                       class="font-semibold text-sm text-foreground hover:underline"
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
                                <span class="badge {{ $app->days_waiting > 14 ? 'badge-destructive' : ($app->days_waiting > 7 ? 'badge-warning' : 'badge-muted') }} tabular-nums">
                                    Menunggu {{ $app->days_waiting }} hari
                                </span>
                                @if ($app->source && (str_contains(strtolower($app->source), 'bot') || str_contains(strtolower($app->source), 'lempar')))
                                    <span class="badge badge-purple" style="font-size:0.625rem;padding:0.125rem 0.375rem;font-weight:600;letter-spacing:0.02em;" title="Di-submit otomatis via {{ $app->source }}">
                                        {{ str_contains(strtolower($app->source), 'lempar') ? '🤖 LemparGawe' : 'Bot' }}
                                    </span>
                                @endif
                                @if ($app->match_score !== null)
                                    <span class="badge {{ $app->match_score >= 75 ? 'badge-success' : ($app->match_score >= 50 ? 'badge-warning' : 'badge-destructive') }} tabular-nums"
                                          title="ATS Match Score: {{ $app->match_score }}%">
                                        {{ $app->match_score }}% Match
                                    </span>
                                @endif
                            </div>

                            @if ($app->salary_note)
                                <div style="margin-bottom:0.5rem;">
                                    <span class="text-xs text-muted">Gaji: <span class="font-medium text-foreground salary-num">{{ $app->salary_note }}</span></span>
                                </div>
                            @endif

                            {{-- Match Analysis & Quick status changer --}}
                            <div class="border-t" style="padding-top:0.5rem;margin-top:auto;">
                                <div style="margin-bottom:0.375rem;">
                                    <button type="button"
                                            class="btn btn-outline btn-sm w-full"
                                            style="height:1.75rem;padding:0 0.5rem;font-size:0.75rem;display:flex;align-items:center;justify-content:center;gap:0.25rem;"
                                            onclick="openMatchModal({{ $app->id }}, '{{ addslashes($app->company) }}', '{{ addslashes($app->position) }}', '{{ addslashes($app->job_description ?? $app->notes ?? '') }}', {{ $app->match_score ?? 'null' }}, {{ json_encode($app->match_analysis ?? null) }})">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.7;"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
                                        <span>{{ $app->match_score !== null ? 'Analisis: ' . $app->match_score . '%' : 'Analisis Kesesuaian' }}</span>
                                    </button>
                                </div>
                                <form method="POST" action="{{ route('applications.quick-status', $app) }}">
                                    @csrf
                                    <label for="status-changer-{{ $app->id }}" class="sr-only">
                                        Ubah status {{ $app->company }}
                                    </label>
                                    <div class="flex items-center gap-1">
                                        <select id="status-changer-{{ $app->id }}"
                                                name="status"
                                                class="select"
                                                style="height:1.75rem;font-size:0.75rem;padding:0 1.75rem 0 0.5rem;flex:1;"
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

{{-- ── Kanban Match Score Modal ────────────────────────────────────────── --}}
<div id="kanbanMatchModal" class="dialog-backdrop" role="dialog" aria-modal="true" aria-labelledby="kanbanMatchTitle">
    <div class="dialog-content" style="max-width: 34rem; width: 95%;">
        <button type="button" class="dialog-close" onclick="closeMatchModal()" aria-label="Tutup">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>

        <div class="dialog-header">
            <h2 class="dialog-title" id="kanbanMatchTitle">AI Match Score &amp; ATS Keyword Gap</h2>
            <p class="dialog-description" id="kanbanMatchSubtitle">Bandingkan deskripsi lowongan dengan profil keahlian Anda.</p>
        </div>

        <form id="kanbanMatchForm" onsubmit="submitMatchAnalysis(event)">
            @csrf
            <input type="hidden" id="matchAppId" name="app_id">

            <div style="margin-bottom: 1rem;">
                <label for="matchDescriptionInput" class="label">Deskripsi / Persyaratan Lowongan</label>
                <textarea id="matchDescriptionInput" name="job_description" rows="4" class="textarea" placeholder="Tempel deskripsi atau kualifikasi lowongan kerja di sini..."></textarea>
            </div>

            <button type="submit" id="btnRunMatch" class="btn btn-default w-full" style="display:flex;align-items:center;justify-content:center;gap:0.375rem;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
                <span id="btnRunMatchText">Analisis Kesesuaian (Match Score)</span>
            </button>
        </form>

        <div id="matchModalResult" style="display: none; margin-top: 1.25rem;">
            {{-- Score Indicator Card --}}
            <div id="matchIndicatorBox" style="border-radius:var(--radius);padding:0.875rem 1rem;margin-bottom:1rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <div>
                        <span id="matchScoreLabel" style="font-size:0.6875rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;"></span>
                        <div id="matchStatusLabel" style="font-size:0.8125rem;font-weight:600;color:hsl(var(--foreground));"></div>
                    </div>
                    <div id="matchScorePercent" class="tabular-nums" style="font-size:1.75rem;font-weight:800;line-height:1;"></div>
                </div>
            </div>

            {{-- Matched Badges --}}
            <div style="margin-bottom: 0.875rem;">
                <div class="info-label" style="color:hsl(142 76% 36%); margin-bottom: 0.25rem;">Kata Kunci Cocok (Matched Keywords)</div>
                <div id="matchedKeywordsContainer" style="display:flex;flex-wrap:wrap;gap:0.25rem;"></div>
            </div>

            {{-- Missing Badges --}}
            <div style="margin-bottom: 0.875rem;">
                <div class="info-label" style="color:hsl(38 92% 40%); margin-bottom: 0.25rem;">Kata Kunci ATS yang Perlu Ditambahkan (Keyword Gap)</div>
                <div id="missingKeywordsContainer" style="display:flex;flex-wrap:wrap;gap:0.25rem;"></div>
            </div>

            {{-- Recommendations --}}
            <div style="padding:0.75rem 0.875rem;background-color:hsl(var(--muted)/0.4);border-radius:calc(var(--radius) - 2px);border-left:3px solid hsl(var(--primary));">
                <div class="info-label" style="margin-bottom:0.25rem;">Rekomendasi AI (Tips CV &amp; Interview)</div>
                <p id="matchRecommendationText" style="font-size:0.8125rem;line-height:1.5;margin:0;"></p>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    var currentAppId = null;

    function openMatchModal(appId, company, position, description, score, analysis) {
        currentAppId = appId;
        document.getElementById('matchAppId').value = appId;
        document.getElementById('kanbanMatchTitle').textContent = 'AI Match Score — ' + company;
        document.getElementById('kanbanMatchSubtitle').textContent = position;
        document.getElementById('matchDescriptionInput').value = description || '';

        var resBox = document.getElementById('matchModalResult');
        if (analysis) {
            renderMatchResult(analysis);
            resBox.style.display = 'block';
        } else {
            resBox.style.display = 'none';
        }

        var modal = document.getElementById('kanbanMatchModal');
        modal.classList.add('open');
    }

    function closeMatchModal() {
        var modal = document.getElementById('kanbanMatchModal');
        modal.classList.remove('open');
    }

    function renderMatchResult(data) {
        var resBox = document.getElementById('matchModalResult');
        var indBox = document.getElementById('matchIndicatorBox');
        var scoreVal = data.match_percentage || data.match_score || 0;

        var isHigh = scoreVal >= 75;
        var isMed = scoreVal >= 50 && scoreVal < 75;
        indBox.style.border = isHigh ? '1px solid hsl(142 76% 36%)' : (isMed ? '1px solid hsl(38 92% 50%)' : '1px solid hsl(0 84.2% 60%)');
        indBox.style.backgroundColor = isHigh ? 'hsl(142 76% 96% / 0.5)' : (isMed ? 'hsl(48 96% 95% / 0.5)' : 'hsl(0 84% 96% / 0.5)');
        var textColor = isHigh ? 'hsl(142 76% 25%)' : (isMed ? 'hsl(38 92% 30%)' : 'hsl(0 84% 40%)');

        document.getElementById('matchScoreLabel').textContent = 'Skor Kesesuaian Profil';
        document.getElementById('matchScoreLabel').style.color = textColor;
        document.getElementById('matchStatusLabel').textContent = isHigh ? 'Sangat Sesuai (High Match)' : (isMed ? 'Cukup Sesuai (Moderate Match)' : 'Perlu Penyesuaian (Low Match)');
        var percentEl = document.getElementById('matchScorePercent');
        percentEl.textContent = scoreVal + '%';
        percentEl.style.color = textColor;

        // Matched
        var matchedCont = document.getElementById('matchedKeywordsContainer');
        matchedCont.innerHTML = '';
        var matched = data.matched_keywords || [];
        if (matched.length > 0) {
            matched.forEach(function(kw) {
                var span = document.createElement('span');
                span.className = 'badge badge-success';
                span.style.fontSize = '0.75rem';
                span.textContent = '✓ ' + kw;
                matchedCont.appendChild(span);
            });
        } else {
            matchedCont.innerHTML = '<span class="text-xs text-muted">Tidak ada kata kunci yang cocok secara spesifik.</span>';
        }

        // Missing
        var missingCont = document.getElementById('missingKeywordsContainer');
        missingCont.innerHTML = '';
        var missing = data.missing_keywords || [];
        if (missing.length > 0) {
            missing.forEach(function(kw) {
                var span = document.createElement('span');
                span.className = 'badge badge-warning';
                span.style.fontSize = '0.75rem';
                span.textContent = '! ' + kw;
                missingCont.appendChild(span);
            });
        } else {
            missingCont.innerHTML = '<span class="badge badge-secondary" style="font-size:0.75rem;">Semua kata kunci utama terpenuhi</span>';
        }

        // Recommendation
        document.getElementById('matchRecommendationText').textContent = data.recommendation || '';
        resBox.style.display = 'block';
    }

    function submitMatchAnalysis(e) {
        e.preventDefault();
        var btn = document.getElementById('btnRunMatch');
        var btnText = document.getElementById('btnRunMatchText');
        var originalText = btnText.textContent;
        btn.disabled = true;
        btnText.textContent = 'Menganalisis...';

        var desc = document.getElementById('matchDescriptionInput').value;
        var tokenInput = document.querySelector('input[name="_token"]');
        var csrfToken = tokenInput ? tokenInput.value : '';

        fetch('/applications/' + currentAppId + '/match', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ job_description: desc })
        })
        .then(function(res) {
            if (!res.ok) throw new Error('Network error');
            return res.json();
        })
        .then(function(data) {
            renderMatchResult(data);
            btn.disabled = false;
            btnText.textContent = originalText;
        })
        .catch(function(err) {
            alert('Gagal melakukan analisis match score: ' + err.message);
            btn.disabled = false;
            btnText.textContent = originalText;
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeMatchModal();
        }
    });

    var modalEl = document.getElementById('kanbanMatchModal');
    if (modalEl) {
        modalEl.addEventListener('click', function(e) {
            if (e.target === this) {
                closeMatchModal();
            }
        });
    }
</script>
@endsection
