<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Pengguna yang wajib ganti password diarahkan ke halaman ganti password (juga saat memulai alur /oauth/authorize). */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password
            && ! $request->routeIs('sso.password', 'sso.password.update', 'sso.logout')
            && ! $request->is('livewire/*')) {
            return redirect()->route('sso.password');
        }

        return $next($request);
    }
}
