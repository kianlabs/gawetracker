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

    </main>
</body>
</html>
