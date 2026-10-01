<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GaweTracker — Tracker Lamaran Kerja Personal</title>
    <link rel="stylesheet" href="{{ asset('css/shadcn.css') }}">
</head>
<body style="background-color:hsl(var(--background));min-height:100vh;display:flex;flex-direction:column;">

    <nav class="welcome-nav">
        <a href="/" class="welcome-nav-brand">
            <span class="brand-logo-badge">G</span>
            <span>GaweTracker</span>
        </a>
        @auth
            <a href="{{ route('dashboard') }}" class="btn btn-outline btn-sm">Dashboard</a>
        @else
            <a href="{{ route('login') }}" class="btn btn-outline btn-sm">Masuk</a>
        @endauth
    </nav>

    <main class="welcome-container" style="flex:1;">

        {{-- ── Hero ── --}}
        <div class="welcome-hero">
            <h1 class="welcome-hero-title">Tracker Lamaran Kerja Personal</h1>
            <p class="welcome-hero-subtitle">Satu tempat untuk semua lamaran kerja Anda.</p>
            <p class="welcome-hero-description">
                GaweTracker membantu Anda mencatat setiap lamaran, melihat posisi pipeline
                real-time, dan menganalisis pola rekrutmen untuk iterasi strategi.
            </p>
        </div>

        {{-- ── Problem statement ── --}}
        <p class="welcome-problem">
            Lamaran kerja sering tercecer di email, spreadsheet, dan ingatan.
            Susah mengingat sudah melamar ke mana, sudah sampai tahap apa,
            dan mana yang perlu di-follow-up.
            GaweTracker memberikan satu dashboard terpusat untuk melacak semua lamaran
            dari awal sampai akhir.
        </p>

        {{-- ── Feature grid ── --}}
        <section class="welcome-section">
            <h2 class="welcome-section-title" style="text-align:center;">Fitur Utama</h2>
            <div class="welcome-features">

                <div class="welcome-feature-card">
                    <div class="welcome-feature-title">Pipeline Visual (Kanban)</div>
                    <p class="welcome-feature-desc">
                        Papan kanban dengan kartu lamaran per tahap: wishlist, applied,
                        screening, interview, offer, hired.
                    </p>
                </div>

                <div class="welcome-feature-card">
                    <div class="welcome-feature-title">Dashboard Metrik KPI</div>
                    <p class="welcome-feature-desc">
                        Total lamaran, aktif diproses, win rate, target mingguan, dan antrean
                        follow-up untuk lamaran &gt;7 hari tanpa kabar.
                    </p>
                </div>

                <div class="welcome-feature-card">
                    <div class="welcome-feature-title">Analisis &amp; Heatmap</div>
                    <p class="welcome-feature-desc">
                        Funnel konversi rekrutmen, rejection breakdown per tahap,
                        time-to-response, dan heatmap aktivitas 12 bulan.
                    </p>
                </div>

                <div class="welcome-feature-card">
                    <div class="welcome-feature-title">Komparasi Offer</div>
                    <p class="welcome-feature-desc">
                        Matriks side-by-side untuk membandingkan salary, benefit,
                        work scheme, dan deadline antar penawaran kerja.
                    </p>
                </div>

                <div class="welcome-feature-card">
                    <div class="welcome-feature-title">Checklist Interview</div>
                    <p class="welcome-feature-desc">
                        Daftar persiapan interview per lamaran: riset perusahaan,
                        latihan soal, portfolio yang akan dibawa.
                    </p>
                </div>

                <div class="welcome-feature-card">
                    <div class="welcome-feature-title">Filter &amp; Export</div>
                    <p class="welcome-feature-desc">
                        Filter berdasarkan status dan work type, search keyword,
                        URL state persistence, dan export ke CSV.
                    </p>
                </div>

            </div>
        </section>

        {{-- ── Tech stack ── --}}
        <section class="welcome-section">
            <h2 class="welcome-section-title" style="text-align:center;">Tech Stack</h2>
            <div class="welcome-tech-badges">
                <span class="welcome-tech-badge">Laravel 13</span>
                <span class="welcome-tech-badge">PHP 8.3</span>
                <span class="welcome-tech-badge">MySQL</span>
                <span class="welcome-tech-badge">Blade</span>
                <span class="welcome-tech-badge">Pure CSS</span>
            </div>
            <p class="welcome-tech-note">
                No Vite/Node toolchain — pure server-side rendering dengan Blade dan CSS tradisional.
            </p>
        </section>

        {{-- ── CTA ── --}}
        <div class="welcome-cta">
            <a href="{{ route('login') }}" class="btn btn-default" style="padding:0.75rem 2rem;font-size:1rem;">
                Masuk ke Dashboard
            </a>
            <p class="welcome-cta-secondary">Single-user authentication — tidak ada registrasi publik</p>
        </div>

    </main>

    <footer style="padding:2rem 1.5rem;text-align:center;border-top:1px solid hsl(var(--border));color:hsl(var(--muted-foreground));font-size:0.875rem;margin-top:3rem;">
        <p>GaweTracker &copy; 2026 — Built with Laravel 13</p>
    </footer>

</body>
</html>
