<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <title>Masuk · Grisa</title>
    @include('sso.styles')
</head>
<body>
<main class="card">
    <div class="brand">Grisa<span>ID</span></div>
    <p class="sub">Masuk dengan <b>NISN</b> (siswa) atau <b>email</b> (guru).</p>

    <form method="POST" action="{{ route('sso.login') }}" novalidate>
        @csrf
        <label for="identifier">NISN atau email</label>
        <input id="identifier" name="identifier" type="text" inputmode="email" autocomplete="username" autocapitalize="none" value="{{ old('identifier') }}" required autofocus>
        @error('identifier') <p class="err">{{ $message }}</p> @enderror

        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>

        <label class="check"><input type="checkbox" name="remember" value="1"> Ingat saya di perangkat ini</label>
        <button type="submit">Masuk</button>
    </form>
    <p class="hint">Pertama kali masuk? Password awal adalah tanggal lahir (ddmmyyyy), lalu kamu diminta menggantinya.</p>
</main>
</body>
</html>
