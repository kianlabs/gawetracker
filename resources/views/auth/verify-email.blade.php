<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Email - GaweTracker</title>
    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
    @include('partials.favicons')
    <link rel="stylesheet" href="{{ asset('css/shadcn.css') }}?v={{ filemtime(public_path('css/shadcn.css')) }}">
</head>
<body class="flex items-center justify-center min-h-screen" style="padding: 1.5rem; background-color: hsl(240 4.8% 97.5%);">
    <main class="card" style="width: 100%; max-width: 24rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);">
        <header class="card-header" style="text-align: center; align-items: center; padding-bottom: 1.25rem;">
            <div class="flex items-center gap-2 mb-2">
                <img src="{{ asset('img/gawetracker-mark.png') }}" alt="" class="brand-logo-img brand-logo-lg" width="36" height="36">
                <h1 class="card-title" style="font-size: 1.375rem; margin-bottom: 0;">GaweTracker</h1>
            </div>
            <p class="card-description">Satu langkah lagi sebelum mulai melacak lamaran Anda</p>
        </header>

        <div class="card-content" style="padding-top: 0;">
            @if (session('status'))
                <div class="alert alert-success" role="alert" style="margin-bottom: 1.25rem;">
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            <p class="text-sm" style="margin-bottom: 1.25rem; color: hsl(var(--muted-foreground));">
                Kami sudah mengirim tautan verifikasi ke <strong>{{ Auth::user()->email }}</strong>.
                Buka tautan itu untuk mengaktifkan akun Anda. Belum menerima emailnya?
            </p>

            <form method="POST" action="{{ route('verification.send') }}" class="space-y-4">
                @csrf
                <button type="submit" class="btn btn-default w-full">
                    Kirim Ulang Email Verifikasi
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}" style="margin-top: 0.75rem;">
                @csrf
                <button type="submit" class="btn btn-outline w-full">
                    Keluar
                </button>
            </form>
        </div>

    </main>
</body>
</html>
