<?php

namespace App\Services;

use App\Enums\StudentStatus;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Akun login guru & siswa. Password awal = tanggal lahir (ddmmyyyy), WAJIB diganti saat masuk pertama.
 *  - Guru  : login dengan email; dibuat dari data guru (email wajib)
 *  - Siswa : login dengan NISN (username); email internal dibuat otomatis
 * Akun staf (admin/bendahara) yang emailnya sama dengan guru hanya DITAUTKAN, passwordnya tidak disentuh.
 * Aman diulang: akun yang sudah ada tidak dibuat ulang.
 */
final class AccountService
{
    /** @return array{dibuat: int, ditautkan: int, sudah_ada: int, dilewati: int, warnings: list<string>} */
    public function createTeachers(): array
    {
        set_time_limit(0);
        $r = $this->blank();

        Teacher::query()->where('status', 'aktif')->orderBy('name')->each(function (Teacher $t) use (&$r): void {
            if (User::query()->where('teacher_id', $t->id)->exists()) {
                $r['sudah_ada']++;

                return;
            }

            $email = mb_strtolower((string) $t->email);
            if ($email === '') {
                $this->skip($r, "{$t->name}: email kosong.");

                return;
            }

            $existing = User::query()->where('email', $email)->first();
            if ($existing) {
                if ($existing->teacher_id === null) {
                    $existing->forceFill(['teacher_id' => $t->id])->save();
                    $r['ditautkan']++;
                } else {
                    $this->skip($r, "{$t->name}: email sudah dipakai akun lain.");
                }

                return;
            }

            $password = $t->birth_date?->format('dmY');
            if ($password === null) {
                $this->skip($r, "{$t->name}: tanggal lahir kosong.");

                return;
            }

            User::query()->forceCreate([
                'name' => $t->full_name, 'email' => $email, 'password' => $this->tempHash($password),
                'account_type' => 'teacher', 'teacher_id' => $t->id, 'must_change_password' => true,
            ]);
            $r['dibuat']++;
        });

        return $r;
    }

    /** @return array{dibuat: int, ditautkan: int, sudah_ada: int, dilewati: int, warnings: list<string>} */
    public function createStudents(): array
    {
        set_time_limit(0);
        $r = $this->blank();

        Student::query()->where('status', StudentStatus::Active->value)->orderBy('full_name')->each(function (Student $s) use (&$r): void {
            if (User::query()->where('student_id', $s->id)->exists()) {
                $r['sudah_ada']++;

                return;
            }

            $nisn = preg_replace('/\D+/', '', (string) $s->nisn);
            if (strlen($nisn) !== 10) {
                $this->skip($r, "{$s->full_name}: NISN belum lengkap.");

                return;
            }

            $password = $s->birth_date?->format('dmY');
            if ($password === null) {
                $this->skip($r, "{$s->full_name}: tanggal lahir kosong.");

                return;
            }

            if (User::query()->where('username', $nisn)->exists()) {
                $this->skip($r, "{$s->full_name}: NISN {$nisn} sudah dipakai akun lain.");

                return;
            }

            User::query()->forceCreate([
                'name' => $s->full_name, 'email' => "{$nisn}@siswa.grisa.local", 'username' => $nisn, 'password' => $this->tempHash($password),
                'account_type' => 'student', 'student_id' => $s->id, 'must_change_password' => true,
            ]);
            $r['dibuat']++;
        });

        return $r;
    }

    /** Kembalikan password ke tanggal lahir; wajib diganti lagi. */
    public function reset(User $user): string
    {
        $birth = match ($user->account_type) {
            'teacher' => Teacher::query()->find($user->teacher_id)?->birth_date,
            'student' => Student::query()->find($user->student_id)?->birth_date,
            default => null,
        };

        if ($birth === null) {
            throw new DomainException('Akun ini bukan akun guru/siswa, atau tanggal lahirnya kosong.');
        }

        $password = $birth->format('dmY');
        DB::transaction(function () use ($user, $password): void {
            $user->forceFill(['password' => $this->tempHash($password), 'must_change_password' => true])->save();
            $user->tokens()->each->revoke(); // semua sesi aplikasi dicabut
        });

        activity('akun')->performedOn($user)->causedBy(auth()->user())->log("Reset password {$user->name}");

        return $password;
    }

    /**
     * Password awal (tanggal lahir) sifatnya sementara dan wajib diganti: cukup bcrypt cost rendah supaya
     * membuat ratusan akun tidak melewati batas waktu. Saat pengguna masuk, Laravel otomatis menaikkan
     * hash ke cost standar (rehash saat login); password baru pilihan pengguna selalu memakai cost standar.
     */
    private function tempHash(string $password): string
    {
        return Hash::driver('bcrypt')->make($password, ['rounds' => 8]);
    }

    /** @return array{dibuat: int, ditautkan: int, sudah_ada: int, dilewati: int, warnings: list<string>} */
    private function blank(): array
    {
        return ['dibuat' => 0, 'ditautkan' => 0, 'sudah_ada' => 0, 'dilewati' => 0, 'warnings' => []];
    }

    private function skip(array &$r, string $why): void
    {
        $r['dilewati']++;
        $r['warnings'][] = $why;
    }
}
