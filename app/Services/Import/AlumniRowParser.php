<?php

namespace App\Services\Import;

use DateTimeInterface;

/**
 * Membaca satu baris file alumni: Nama, L/P, NIK, NISN, Tanggal Lahir, Nama Ibu, Rombel Terakhir.
 * Setiap sheet = satu tahun lulus (nama sheet: 2020, 2021, ...). Murni PHP, mudah diuji.
 */
final class AlumniRowParser
{
    private const HEADERS = [
        'name' => ['nama', 'namasiswa', 'namalengkap'],
        'gender' => ['l/p', 'lp', 'jk', 'jeniskelamin'],
        'nik' => ['nik'],
        'nisn' => ['nisn'],
        'birth_date' => ['tanggallahir', 'tgllahir', 'tanggal_lahir'],
        'mother' => ['namaibu', 'ibu', 'namaibukandung'],
        'rombel' => ['rombelterakhir', 'rombel', 'kelasterakhir', 'kelas'],
    ];

    /** @return array<string, int>|null kunci => indeks kolom; null bila kolom wajib (Nama, NISN) tidak ada */
    public function headerMap(array $row): ?array
    {
        $found = [];

        foreach ($row as $index => $cell) {
            $norm = preg_replace('/\s+/', '', mb_strtolower(trim((string) $cell)));

            foreach (self::HEADERS as $key => $aliases) {
                if (! isset($found[$key]) && in_array($norm, $aliases, true)) {
                    $found[$key] = (int) $index;
                }
            }
        }

        return (isset($found['name'], $found['nisn'])) ? $found : null;
    }

    /**
     * @param  array<string, int>  $map
     * @return array{data: array<string, mixed>, warnings: list<string>, errors: list<string>}
     */
    public function parse(array $row, array $map): array
    {
        $warnings = [];
        $errors = [];
        $cell = fn (string $key): mixed => isset($map[$key]) ? ($row[$map[$key]] ?? null) : null;

        $name = $this->text($cell('name'));

        if ($name === null) {
            $errors[] = 'Nama kosong';
        }

        $gender = $this->gender($cell('gender'));

        if ($gender === null) {
            $errors[] = 'Jenis kelamin tidak terbaca';
        }

        [$nisn, $nisnWarning] = $this->nisn($cell('nisn'));

        if ($nisn === null) {
            $errors[] = $nisnWarning ?? 'NISN kosong';
        } elseif ($nisnWarning !== null) {
            $warnings[] = $nisnWarning;
        }

        $nik = $this->digits($cell('nik'));

        if ($nik !== null && strlen($nik) !== 16) {
            $warnings[] = 'NIK tidak 16 digit, tidak disimpan: '.$nik;
            $nik = null;
        }

        $birthRaw = $cell('birth_date');
        $birth = $this->date($birthRaw);

        if ($birth === null && $this->text($birthRaw) !== null) {
            $warnings[] = 'Tanggal lahir tidak valid: '.$this->text($birthRaw);
        }

        $mother = $this->text($cell('mother'));

        if ($mother !== null && in_array(mb_strtolower($mother), ['-', 'undefined', 'null'], true)) {
            $mother = null;
        }

        return [
            'data' => ['full_name' => $name, 'gender' => $gender, 'nisn' => $nisn, 'nik' => $nik, 'birth_date' => $birth, 'mother' => $mother],
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    /** Nama sheet harus tahun lulus yang masuk akal; selain itu sheet dilewati. */
    public function graduationYear(string $sheetTitle, int $currentYear): ?int
    {
        $title = trim($sheetTitle);

        if (! preg_match('/^\d{4}$/', $title)) {
            return null;
        }

        $year = (int) $title;

        return ($year >= 1990 && $year <= $currentYear + 1) ? $year : null;
    }

    private function text(mixed $value): ?string
    {
        if ($value === null || is_array($value)) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $value));

        return $text === '' ? null : $text;
    }

    private function digits(mixed $value): ?string
    {
        if (is_float($value) && floor($value) === $value) {
            $value = number_format($value, 0, '', ''); // NIK yang terbaca sebagai angka (tanpa notasi ilmiah)
        }

        $digits = preg_replace('/\D+/', '', (string) ($this->text($value) ?? ''));

        return $digits === '' ? null : $digits;
    }

    private function gender(mixed $value): ?string
    {
        $v = mb_strtoupper((string) ($this->text($value) ?? ''));

        return match (true) {
            $v === 'L', str_starts_with($v, 'LAKI') => 'L',
            $v === 'P', str_starts_with($v, 'PEREMPUAN') => 'P',
            default => null,
        };
    }

    /** @return array{0: ?string, 1: ?string} [nisn, peringatan/galat] */
    private function nisn(mixed $value): array
    {
        $digits = $this->digits($value);

        if ($digits === null) {
            return [null, 'NISN kosong'];
        }

        if (strlen($digits) > 10) {
            return [null, 'NISN lebih dari 10 digit: '.$digits];
        }

        if (strlen($digits) < 10) {
            $padded = str_pad($digits, 10, '0', STR_PAD_LEFT);

            return [$padded, "NISN {$digits} kurang dari 10 digit, dilengkapi nol di depan menjadi {$padded}"];
        }

        return [$digits, null];
    }

    private function date(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            $serial = (float) $value;

            if ($serial >= 1 && $serial < 80000) { // nomor seri tanggal Excel
                return gmdate('Y-m-d', (int) round(($serial - 25569) * 86400));
            }

            return null;
        }

        $text = $this->text($value);

        if ($text === null) {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y'] as $format) {
            $d = \DateTime::createFromFormat('!'.$format, $text);

            if ($d !== false && $d->format($format) === $text) {
                return $d->format('Y-m-d');
            }
        }

        return null;
    }
}
