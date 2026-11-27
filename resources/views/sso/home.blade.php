<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <title>Akun saya · Grisa</title>
    @include('sso.styles')
</head>
<body>
<main class="card">
    <div class="brand">Grisa<span>ID</span></div>
    <p class="sub">Kamu sudah masuk.</p>

    <p style="margin:0;font-size:1.1rem;font-weight:700">{{ $user->name }}</p>
    <p class="hint" style="margin:.25rem 0 0">{{ $user->account_type === 'student' ? 'Siswa' : 'Guru' }}@if ($detail) · {{ $detail }}@endif</p>

    @if (session('status'))
        <p class="hint" style="color:var(--brand)">{{ session('status') }}</p>
    @endif

    <p class="hint" style="margin-top:1.25rem">Akun ini dipakai untuk masuk ke aplikasi Grisa lainnya, seperti GrisaKAD, yang segera hadir. Tidak ada yang perlu dilakukan di halaman ini.</p>

    <a href="{{ route('sso.password') }}" style="display:block;margin-top:1.25rem;text-align:center;padding:.8rem;border:1px solid var(--line);border-radius:.6rem;color:inherit;text-decoration:none;font-weight:600">Ganti password</a>

    <form method="POST" action="{{ route('sso.logout') }}" style="margin-top:.5rem">
        @csrf
        <button type="submit" class="ghost">Keluar</button>
    </form>
</main>
</body>
</html>
