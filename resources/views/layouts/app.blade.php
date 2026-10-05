<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'GaweTracker') — GaweTracker</title>
    {{-- Self-hosted font stylesheet. Loaded alongside the main stylesheet so
         the @font-face rules and the CSS that uses them arrive together. --}}
    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
    {{-- The stylesheet is served with a one-day Cache-Control and Cloudflare
         caches it at the edge, so a plain URL keeps serving the previous
         build. Key the URL on the file mtime to invalidate both caches the
         moment the file changes. --}}
    <link rel="stylesheet" href="{{ asset('css/shadcn.css') }}?v={{ filemtime(public_path('css/shadcn.css')) }}">
    @yield('styles')
</head>
<body>
    {{-- ── Navigation ──────────────────────────────────────────────────── --}}
    <header class="site-header">
        <div class="nav-container">
            <div class="brand-group">
                <a href="{{ route('dashboard') }}" class="brand-link">
                    <span class="brand-logo-badge">G</span>
                    <span>GaweTracker</span>
                </a>
                <nav class="main-nav" aria-label="Navigasi utama">
                    <a href="{{ route('dashboard') }}"
                       class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                       aria-current="{{ request()->routeIs('dashboard') ? 'page' : 'false' }}">
                        Dashboard
                    </a>
                    <a href="{{ route('applications.index') }}"
                       class="nav-item {{ request()->routeIs('applications.index', 'applications.show', 'applications.edit', 'applications.create') ? 'active' : '' }}"
                       aria-current="{{ request()->routeIs('applications.index', 'applications.show', 'applications.edit', 'applications.create') ? 'page' : 'false' }}">
                        Lamaran
                    </a>
                    <a href="{{ route('applications.kanban') }}"
                       class="nav-item {{ request()->routeIs('applications.kanban') ? 'active' : '' }}"
                       aria-current="{{ request()->routeIs('applications.kanban') ? 'page' : 'false' }}">
                        Kanban
                    </a>
                    <a href="{{ route('discovery.index') }}"
                       class="nav-item {{ request()->routeIs('discovery.*') ? 'active' : '' }}"
                       aria-current="{{ request()->routeIs('discovery.*') ? 'page' : 'false' }}">
                        Cari Kerja
                    </a>
                    <a href="{{ route('analytics.index') }}"
                       class="nav-item {{ request()->routeIs('analytics.*') ? 'active' : '' }}"
                       aria-current="{{ request()->routeIs('analytics.*') ? 'page' : 'false' }}">
                        Analisis
                    </a>
                    <a href="{{ route('offers.index') }}"
                       class="nav-item {{ request()->routeIs('offers.*') ? 'active' : '' }}"
                       aria-current="{{ request()->routeIs('offers.*') ? 'page' : 'false' }}">
                        Offer
                    </a>
                </nav>
            </div>

            <div class="user-nav">
                <span class="user-label">{{ Auth::user()->name }}</span>
                @if (Auth::user()->hasGmailConnected())
                    <form method="POST" action="{{ route('gmail.disconnect') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline btn-sm"
                                title="Gmail: {{ Auth::user()->gmail_email ?? 'terhubung' }} — klik untuk memutuskan">
                            Gmail terhubung
                        </button>
                    </form>
                @else
                    <a href="{{ route('gmail.connect') }}" class="btn btn-outline btn-sm">Hubungkan Gmail</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline btn-sm">Keluar</button>
                </form>
            </div>
        </div>
    </header>

    {{-- ── Main content ────────────────────────────────────────────────── --}}
    <main class="main-content container" id="main-content">
        @if (session('success'))
            <div class="alert alert-success" role="status" aria-live="polite">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-error" role="alert" aria-live="assertive">
                {{ session('error') }}
            </div>
        @endif

        @yield('content')
    </main>

    {{-- ── Footer ──────────────────────────────────────────────────────── --}}
    <footer class="site-footer">
        <div class="container flex items-center justify-between flex-wrap gap-2 text-xs text-muted">
            <div class="flex items-center gap-2">
                <span class="font-semibold text-foreground">GaweTracker</span>
                <span>&bull;</span>
                <span>Personal Job Application Tracker</span>
            </div>
            <span>
                <kbd class="kbd">Q</kbd>
                Quick Add lamaran baru
            </span>
        </div>
    </footer>

    @yield('scripts')
</body>
</html>
