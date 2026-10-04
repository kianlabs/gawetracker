<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - GaweTracker</title>
    <link rel="stylesheet" href="{{ asset('css/shadcn.css') }}">
</head>
<body class="flex items-center justify-center min-h-screen" style="padding: 1.5rem; background-color: hsl(240 4.8% 97.5%);">
    <main class="card" style="width: 100%; max-width: 24rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);">
        <header class="card-header" style="text-align: center; align-items: center; padding-bottom: 1.25rem;">
            <div class="flex items-center gap-2 mb-2">
                <span class="brand-logo-badge" style="width: 2rem; height: 2rem; font-size: 1rem;">G</span>
                <h1 class="card-title" style="font-size: 1.375rem; margin-bottom: 0;">GaweTracker</h1>
            </div>
            <p class="card-description">Masuk ke akun tracker lamaran kerja Anda</p>
        </header>

        <div class="card-content" style="padding-top: 0;">
            @if (session('error'))
                <div class="alert alert-error" role="alert" style="margin-bottom: 1.25rem;">
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-error" role="alert" style="margin-bottom: 1.25rem;">
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            @if ($googleEnabled)
                <a href="{{ route('auth.google') }}" class="btn btn-outline w-full"
                   style="display:flex;align-items:center;justify-content:center;gap:0.5rem;margin-bottom:1rem;">
                    <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true" focusable="false">
                        <path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.72v2.26h2.92c1.7-1.57 2.68-3.88 2.68-6.62z"/>
                        <path fill="#34A853" d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.92-2.26c-.8.54-1.84.86-3.04.86-2.34 0-4.32-1.58-5.03-3.7H.96v2.33A9 9 0 0 0 9 18z"/>
                        <path fill="#FBBC05" d="M3.97 10.72a5.4 5.4 0 0 1 0-3.44V4.95H.96a9 9 0 0 0 0 8.1l3.01-2.33z"/>
                        <path fill="#EA4335" d="M9 3.58c1.32 0 2.5.45 3.44 1.35l2.58-2.58C13.46.9 11.43 0 9 0A9 9 0 0 0 .96 4.95l3.01 2.33C4.68 5.16 6.66 3.58 9 3.58z"/>
                    </svg>
                    Masuk dengan Google
                </a>

                <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1rem;">
                    <span style="flex:1;height:1px;background:hsl(var(--border));"></span>
                    <span class="text-xs text-muted">atau</span>
                    <span style="flex:1;height:1px;background:hsl(var(--border));"></span>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" novalidate class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="label">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="input @error('email') input-error @enderror"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="nama@email.com"
                    >
                    @error('email')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="label">Kata Sandi</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="input @error('password') input-error @enderror"
                        required
                        autocomplete="current-password"
                        placeholder="••••••••"
                    >
                    @error('password')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center gap-2" style="margin-top: 0.5rem; margin-bottom: 0.5rem;">
                    <input
                        type="checkbox"
                        id="remember"
                        name="remember"
                        {{ old('remember') ? 'checked' : '' }}
                        style="width: 1rem; height: 1rem; border-radius: 4px; border: 1px solid hsl(var(--input)); accent-color: hsl(var(--primary)); cursor: pointer;"
                    >
                    <label for="remember" class="text-sm font-normal text-muted" style="margin-bottom: 0; cursor: pointer;">
                        Ingat saya
                    </label>
                </div>

                <button type="submit" class="btn btn-default w-full">
                    Masuk
                </button>
            </form>
        </div>

        <div class="card-footer" style="padding-top: 0.75rem; border-top: 1px dashed hsl(var(--border)); justify-content: center;">
            <p class="text-xs text-muted text-center" style="line-height: 1.5;">
                Akun Default: <strong style="color: hsl(var(--foreground));">kyan@gawetracker.test</strong> / <strong style="color: hsl(var(--foreground));">password</strong>
            </p>
        </div>
    </main>
</body>
</html>
