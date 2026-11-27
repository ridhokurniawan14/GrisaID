<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <title>Ganti password · Grisa</title>
    @include('sso.styles')
</head>
<body>
<main class="card">
    <div class="brand">Grisa<span>ID</span></div>
    <p class="sub">{{ $forced ? 'Untuk keamanan, ganti password awalmu sebelum melanjutkan.' : 'Ganti password.' }}</p>

    <form method="POST" action="{{ route('sso.password.update') }}" novalidate>
        @csrf
        <label for="current_password">Password saat ini</label>
        <input id="current_password" name="current_password" type="password" autocomplete="current-password" required autofocus>
        @error('current_password') <p class="err">{{ $message }}</p> @enderror

        <label for="password">Password baru</label>
        <input id="password" name="password" type="password" autocomplete="new-password" required>
        <p class="hint" style="margin-top:.25rem">Minimal 8 karakter, ada huruf dan angka. Jangan sama dengan tanggal lahir atau NISN.</p>
        @error('password') <p class="err">{{ $message }}</p> @enderror

        <label for="password_confirmation">Ulangi password baru</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>

        <button type="submit">Simpan & lanjut</button>
    </form>

    <form method="POST" action="{{ route('sso.logout') }}" style="margin-top:.75rem">
        @csrf
        <button type="submit" class="ghost">Keluar</button>
    </form>
</main>
</body>
</html>
