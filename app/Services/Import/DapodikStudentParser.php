<?php

namespace App\Services\Import;

/**
 * Mengubah satu baris mentah Excel Dapodik (array berindeks kolom) menjadi data bersih.
 * Murni PHP, tanpa database, supaya mudah diuji.
 *
 * Tahan terhadap keanehan export Dapodik:
 *  - kolom NISN dan JK kadang tertukar posisinya (dideteksi dari ISI, bukan posisi)
 *  - sheet tanpa baris header / baris judul nyelip di tengah (baris data dikenali dari isinya)
 *  - nama huruf besar semua / kecil semua (dinormalkan jadi Title Case)
 */
final class DapodikStudentParser
{
    /** Kode jurusan di nama rombel Dapodik => kode jurusan GrisaID. */
    public const MAJOR_ALIASES = [
        'AKL' => 'AKL',
        'KULINER' => 'KUL',
        'KUL' => 'KUL',
        'MPLB' => 'MPLB',
        'PH' => 'PH',
        'PM' => 'PM',
        'TJKT' => 'TJKT',
        'TKJ' => 'TJKT',
    ];

    private const GRADES = ['X' => 10, 'XI' => 11, 'XII' => 12];

    // Indeks kolom export "Daftar Peserta Didik" Dapodik (sama di semua sheet).
    private const C_NAME = 1;
    private const C_NIPD = 2;
    private const C_GENDER_A = 3;   // JK atau NISN (bisa tertukar)
    private const C_GENDER_B = 4;   // NISN atau JK
    private const C_BIRTH_PLACE = 5;
    private const C_BIRTH_DATE = 6;
    private const C_ADDRESS = 9;
    private const C_RT = 10;
    private const C_RW = 11;
    private const C_HAMLET = 12;
    private const C_VILLAGE = 13;
    private const C_DISTRICT = 14;
    private const C_MOBILE = 19;
    private const C_EMAIL = 20;
    private const C_ROMBEL = 42;

    /** relasi => [kolom nama, kolom pekerjaan] */
    private const GUARDIAN_COLUMNS = [
        'ayah' => [24, 27],
        'ibu' => [30, 33],
        'wali' => [36, 39],
    ];

    /** Baris data = kolom No berupa angka DAN kolom Nama berupa teks tidak kosong. */
    public function isDataRow(array $row): bool
    {
        return isset($row[0], $row[1])
            && is_numeric($row[0])
            && is_string($row[1])
            && trim($row[1]) !== '';
    }

    /**
     * @return array{data: array<string, mixed>, warnings: list<string>, errors: list<string>}
     */
    public function parse(array $row): array
    {
        $warnings = [];
        $errors = [];

        $name = $this->cleanName($row[self::C_NAME] ?? null);
        if ($name === null) {
            $errors[] = 'Nama kosong';
        }

        [$gender, $nisnRaw] = $this->genderAndNisn($row[self::C_GENDER_A] ?? null, $row[self::C_GENDER_B] ?? null);
        if ($gender === null) {
            $errors[] = 'Jenis kelamin tidak terbaca';
        }

        [$nisn, $nisnWarning] = $this->nisn($nisnRaw);
        if ($nisnWarning !== null) {
            $warnings[] = $nisnWarning;
        }

        $nis = $this->str($row[self::C_NIPD] ?? null);
        if ($nisn === null && $nis === null) {
            $errors[] = 'NISN dan NIPD sama-sama kosong (siswa tidak bisa diidentifikasi)';
        }

        $birthRaw = $row[self::C_BIRTH_DATE] ?? null;
        $birthDate = $this->date($birthRaw);
        if ($birthDate === null) {
            $warnings[] = $this->str($birthRaw) === null ? 'Tanggal lahir kosong' : 'Tanggal lahir tidak valid: ' . $this->str($birthRaw);
        }

        $rombelRaw = $this->str($row[self::C_ROMBEL] ?? null);
        $rombel = $this->parseRombel($rombelRaw);
        if ($rombel === null) {
            $errors[] = $rombelRaw === null ? 'Rombel kosong' : "Format rombel tidak dikenali: {$rombelRaw}";
        } elseif ($rombel['major_code'] === null) {
            $errors[] = "Kode jurusan '{$rombel['major_key']}' pada rombel {$rombel['name']} belum dipetakan";
        }

        $emailRaw = $this->str($row[self::C_EMAIL] ?? null);
        $email = $emailRaw === null ? null : (filter_var(strtolower($emailRaw), FILTER_VALIDATE_EMAIL) ?: null);
        if ($emailRaw !== null && $email === null) {
            $warnings[] = "Email tidak valid: {$emailRaw}";
        }

        return [
            'data' => [
                'nisn' => $nisn,
                'nis' => $nis,
                'full_name' => $name,
                'gender' => $gender,
                'birth_place' => $this->cleanName($row[self::C_BIRTH_PLACE] ?? null),
                'birth_date' => $birthDate,
                'address' => $this->address($row),
                'phone' => $this->phone($row[self::C_MOBILE] ?? null),
                'email' => $email,
                'rombel' => $rombel,
                'guardians' => $this->guardians($row),
            ],
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    /**
     * "XII KULINER 1" => grade 12, major KUL, group 1.  "X AKL" => group null.
     *
     * @return array{name: string, grade: int, major_key: string, major_code: ?string, group_no: ?int}|null
     */
    public function parseRombel(?string $value): ?array
    {
        if ($value === null) {
            return null;
        }

        $name = strtoupper(preg_replace('/\s+/', ' ', trim($value)));

        if (! preg_match('/^(XII|XI|X)\s+(.+?)(?:\s+(\d+))?$/', $name, $m)) {
            return null;
        }

        $key = trim($m[2]);

        return [
            'name' => $name,
            'grade' => self::GRADES[$m[1]],
            'major_key' => $key,
            'major_code' => self::MAJOR_ALIASES[$key] ?? null,
            'group_no' => isset($m[3]) ? (int) $m[3] : null,
        ];
    }

    /** @return array{0: ?string, 1: mixed} [gender, nisnMentah] */
    private function genderAndNisn(mixed $a, mixed $b): array
    {
        if (($g = $this->gender($a)) !== null) {
            return [$g, $b];
        }

        if (($g = $this->gender($b)) !== null) {
            return [$g, $a];
        }

        return [null, $a ?? $b];
    }

    private function gender(mixed $v): ?string
    {
        $u = strtoupper(trim((string) ($v ?? '')));

        return match ($u) {
            'L', 'LAKI-LAKI', 'LAKI LAKI' => 'L',
            'P', 'PEREMPUAN' => 'P',
            default => null,
        };
    }

    /** @return array{0: ?string, 1: ?string} [nisn, peringatan] */
    private function nisn(mixed $v): array
    {
        if ($v === null || trim((string) $v) === '') {
            return [null, 'NISN kosong'];
        }

        if (is_int($v) || is_float($v)) {
            $digits = number_format((float) $v, 0, '', '');
            if (strlen($digits) < 10) {
                return [str_pad($digits, 10, '0', STR_PAD_LEFT), 'NISN tersimpan sebagai angka di Excel; nol di depan dipulihkan, mohon dicek'];
            }
        } else {
            $digits = preg_replace('/\D+/', '', (string) $v);
        }

        return strlen($digits) === 10
            ? [$digits, null]
            : [null, 'NISN tidak valid (harus 10 digit): ' . trim((string) $v)];
    }

    private function date(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }

        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }

        // Serial tanggal Excel (mis. 39806).
        if (is_int($v) || is_float($v) || (is_string($v) && is_numeric($v))) {
            $n = (float) $v;

            return ($n > 20000 && $n < 80000) ? gmdate('Y-m-d', (int) round(($n - 25569) * 86400)) : null;
        }

        $s = trim((string) $v);

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }

        if (preg_match('#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$#', $s, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        return null;
    }

    /** Nomor HP ke format lokal 08xxxxxxxxxx. Hanya kolom HP (telepon rumah diabaikan). */
    private function phone(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }

        $d = is_float($v) ? number_format($v, 0, '', '') : (string) $v;
        $d = preg_replace('/\D+/', '', $d);

        if ($d === '') {
            return null;
        }

        if (str_starts_with($d, '62')) {
            $d = '0' . substr($d, 2);
        } elseif (str_starts_with($d, '8')) {
            $d = '0' . $d;
        }

        return (str_starts_with($d, '0') && strlen($d) >= 9 && strlen($d) <= 15) ? $d : null;
    }

    private function address(array $row): ?string
    {
        $rt = $this->str($row[self::C_RT] ?? null);
        $rw = $this->str($row[self::C_RW] ?? null);

        $parts = array_filter([
            $this->str($row[self::C_ADDRESS] ?? null),
            ($rt !== null || $rw !== null) ? 'RT ' . ($rt ?? '-') . '/RW ' . ($rw ?? '-') : null,
            $this->str($row[self::C_HAMLET] ?? null),
            $this->str($row[self::C_VILLAGE] ?? null),
            $this->str($row[self::C_DISTRICT] ?? null),
        ], fn($p) => $p !== null);

        return $parts === [] ? null : implode(', ', $parts);
    }

    /** @return array<string, array{name: string, occupation: ?string}> */
    private function guardians(array $row): array
    {
        $out = [];

        foreach (self::GUARDIAN_COLUMNS as $relation => [$nameCol, $jobCol]) {
            $name = $this->cleanName($row[$nameCol] ?? null);

            if ($name === null || $name === '0') {
                continue;
            }

            $out[$relation] = ['name' => $name, 'occupation' => $this->str($row[$jobCol] ?? null)];
        }

        return $out;
    }

    private function cleanName(mixed $v): ?string
    {
        $s = $this->str($v);

        if ($s === null || $s === '-') {
            return null;
        }

        return mb_convert_case($s, MB_CASE_TITLE, 'UTF-8');
    }

    /** Trim + rapikan spasi; string kosong menjadi null. */
    private function str(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }

        $s = trim(preg_replace('/\s+/', ' ', is_float($v) ? number_format($v, 0, '', '') : (string) $v));

        return $s === '' ? null : $s;
    }
}
