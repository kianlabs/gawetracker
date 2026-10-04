<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - GaweTracker</title>
    <link rel="stylesheet" href="{{ asset('css/shadcn.css') }}">
</head>
<body class="flex items-center justify-center min-h-screen" style="padding: 1.5rem; background-color: hsl(240 4.8% 97.5%);">
    <main class="card" style="width: 100%; max-width: 24rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);">
        <header class="card-header" style="text-align: center; align-items: center; padding-bottom: 1.25rem;">
            <div class="flex items-center gap-2 mb-2">
                <span class="brand-logo-badge" style="width: 2rem; height: 2rem; font-size: 1rem;">G</span>
                <h1 class="card-title" style="font-size: 1.375rem; margin-bottom: 0;">GaweTracker</h1>
            </div>
            <p class="card-description">Buat akun untuk mulai melacak lamaran kerja Anda</p>
        </header>

        <div class="card-content" style="padding-top: 0;">
            @if ($errors->any())
                <div class="alert alert-error" role="alert" style="margin-bottom: 1.25rem;">
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('register') }}" novalidate class="space-y-4">
                @csrf

                <div>
                    <label for="name" class="label">Nama Lengkap</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        class="input @error('name') input-error @enderror"
                        value="{{ old('name') }}"
                        required
                        autofocus
                        autocomplete="name"
                        placeholder="Nama Anda"
                    >
                    @error('name')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="label">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="input @error('email') input-error @enderror"
                        value="{{ old('email') }}"
                        required
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
                        autocomplete="new-password"
                        placeholder="Minimal 8 karakter"
                    >
                    @error('password')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="label">Konfirmasi Kata Sandi</label>
                    <input
                        type="password"
                        id="password_confirmation"
                        name="password_confirmation"
                        class="input"
                        required
                        autocomplete="new-password"
                        placeholder="Ulangi kata sandi"
                    >
                </div>

                <button type="submit" class="btn btn-default w-full">
                    Daftar
                </button>
            </form>

            <p class="text-sm text-muted" style="text-align: center; margin-top: 1.25rem; margin-bottom: 0;">
                Sudah punya akun?
                <a href="{{ route('login') }}" style="color: hsl(var(--primary)); font-weight: 500;">Masuk di sini</a>
            </p>
        </div>

    </main>
</body>
</html>
