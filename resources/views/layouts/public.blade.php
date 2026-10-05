<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'GaweTracker') — GaweTracker</title>
    <meta name="description" content="@yield('meta_description', 'GaweTracker — pelacak lamaran kerja pribadi untuk mencatat, mengelola, dan memantau status lamaran Anda.')">
    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('css/shadcn.css') }}?v={{ filemtime(public_path('css/shadcn.css')) }}">
    @yield('styles')
</head>
<body class="public-body">
    {{-- ── Navigation ──────────────────────────────────────────────────── --}}
    <header class="site-header">
        <div class="nav-container">
            <div class="brand-group">
                <a href="{{ route('about') }}" class="brand-link">
                    <span class="brand-logo-badge">G</span>
                    <span>GaweTracker</span>
                </a>
                <nav class="main-nav" aria-label="Navigasi utama">
                    <a href="{{ route('about') }}"
                       class="nav-item {{ request()->routeIs('about') ? 'active' : '' }}">
                        Tentang
                    </a>
                    <a href="{{ route('privacy') }}"
                       class="nav-item {{ request()->routeIs('privacy') ? 'active' : '' }}">
                        Privasi
                    </a>
                    <a href="{{ route('terms') }}"
                       class="nav-item {{ request()->routeIs('terms') ? 'active' : '' }}">
                        Ketentuan
                    </a>
                </nav>
            </div>

            <div class="user-nav">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-outline btn-sm">Buka Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-outline btn-sm">Masuk</a>
                    @if (config('auth.registration_enabled'))
                        <a href="{{ route('register') }}" class="btn btn-default btn-sm">Daftar</a>
                    @endif
                @endauth
            </div>
        </div>
    </header>

    {{-- ── Main content ────────────────────────────────────────────────── --}}
    <main class="main-content container" id="main-content">
        <article class="card public-doc">
            <header class="card-header">
                <h1 class="card-title public-title">@yield('heading', View::getSection('title'))</h1>
                @hasSection('subtitle')
                    <p class="card-description">@yield('subtitle')</p>
                @endif
            </header>
            <div class="card-content public-prose">
                @yield('content')
            </div>
        </article>
    </main>

    {{-- ── Footer ──────────────────────────────────────────────────────── --}}
    <footer class="site-footer">
        <div class="container flex items-center justify-between flex-wrap gap-2 text-xs text-muted">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="font-semibold text-foreground">GaweTracker</span>
                <span>&bull;</span>
                <span>Personal Job Application Tracker</span>
                <span>&bull;</span>
                <a href="{{ route('privacy') }}">Kebijakan Privasi</a>
                <span>&bull;</span>
                <a href="{{ route('terms') }}">Ketentuan Layanan</a>
            </div>
            <span>&copy; {{ date('Y') }} GaweTracker</span>
        </div>
    </footer>

    @yield('scripts')
</body>
</html>
