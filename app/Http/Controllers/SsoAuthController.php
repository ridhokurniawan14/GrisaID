<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/** Halaman masuk tunggal (SSO) untuk semua aplikasi Grisa: NISN (siswa) atau email (guru/staf) + password. */
class SsoAuthController extends Controller
{
    public function showLogin()
    {
        return view('sso.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['identifier' => ['required', 'string', 'max:255'], 'password' => ['required', 'string']]);
        $id = trim($data['identifier']);
        $key = 'sso-login:' . Str::lower($id) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['identifier' => 'Terlalu banyak percobaan. Coba lagi dalam ' . RateLimiter::availableIn($key) . ' detik.']);
        }

        $field = str_contains($id, '@') ? 'email' : 'username';
        $credentials = [$field => $field === 'email' ? mb_strtolower($id) : preg_replace('/\D+/', '', $id), 'password' => $data['password']];

        if (! Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);

            // Pesan sama untuk "akun tidak ada" dan "password salah".
            throw ValidationException::withMessages(['identifier' => 'NISN/email atau password salah.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended(route('sso.home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /** Halaman setelah masuk tanpa tujuan lain: staf ke panel admin, siswa/guru ke halaman akun. */
    public function home(Request $request)
    {
        $user = $request->user();

        if ($user->account_type === 'staff') {
            return redirect('/admin');
        }

        $detail = null;

        if ($user->account_type === 'student') {
            $detail = Student::query()->find($user->student_id)?->nisn ? 'NISN ' . Student::query()->find($user->student_id)->nisn : null;
        } elseif ($user->account_type === 'teacher') {
            $detail = Teacher::query()->find($user->teacher_id)?->email;
        }

        return view('sso.home', ['user' => $user, 'detail' => $detail]);
    }

    public function showChangePassword()
    {
        return view('sso.change-password', ['forced' => (bool) auth()->user()->must_change_password]);
    }

    public function changePassword(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], ['current_password.current_password' => 'Password saat ini salah.', 'password.confirmed' => 'Konfirmasi password tidak sama.']);

        // Tidak boleh sama dengan password awal (tanggal lahir) atau NISN.
        $birth = match ($user->account_type) {
            'student' => Student::query()->find($user->student_id)?->birth_date,
            'teacher' => Teacher::query()->find($user->teacher_id)?->birth_date,
            default => null,
        };
        $weak = array_filter([$birth?->format('dmY'), $user->username]);

        if (in_array($request->input('password'), $weak, true) || Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages(['password' => 'Password baru tidak boleh sama dengan password awal/lama atau NISN.']);
        }

        $user->forceFill(['password' => Hash::make($request->input('password')), 'must_change_password' => false])->save();
        $request->session()->regenerate();

        return redirect()->intended(route('sso.home'))->with('status', 'Password diperbarui.');
    }
}
