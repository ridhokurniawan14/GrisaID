<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <title>Izinkan aplikasi · Grisa</title>
    @include('sso.styles')
</head>
<body>
<main class="card">
    <div class="brand">Grisa<span>ID</span></div>
    <p class="sub"><b>{{ $client->name }}</b> meminta izin mengakses akunmu ({{ $user->name }}).</p>

    @if (count($scopes) > 0)
        <ul class="hint" style="margin:0 0 1rem;padding-left:1.1rem">
            @foreach ($scopes as $scope)
                <li>{{ $scope->description }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('passport.authorizations.approve') }}">
        @csrf
        <input type="hidden" name="state" value="{{ $request->state }}">
        <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
        <input type="hidden" name="auth_token" value="{{ $authToken }}">
        <button type="submit">Izinkan</button>
    </form>

    <form method="POST" action="{{ route('passport.authorizations.deny') }}" style="margin-top:.5rem">
        @csrf
        @method('DELETE')
        <input type="hidden" name="state" value="{{ $request->state }}">
        <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
        <input type="hidden" name="auth_token" value="{{ $authToken }}">
        <button type="submit" class="ghost">Tolak</button>
    </form>
</main>
</body>
</html>
