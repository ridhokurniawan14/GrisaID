<?php

namespace App\Services\Import;

use App\Models\Teacher;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Impor "Daftar Guru" Dapodik (xlsx langsung unduhan). Baris judul dicari otomatis (baris yang memuat Nama & NIK).
 * Dicocokkan lewat NIK, lalu email, lalu NUPTK. Aman diulang.
 * Nama & gelar yang sudah ada TIDAK ditimpa (Dapodik tidak memuat gelar; gelar diisi manual).
 * Mode simulasi: semua dijalankan di dalam transaksi lalu di-rollback.
 */
final class DapodikTeacherImporter
{
    /** @return array{created: int, updated: int, failed: int, warnings: list<string>, errors: list<string>} */
    public function import(string $path, bool $dryRun = true): array
    {
        $r = ['created' => 0, 'updated' => 0, 'failed' => 0, 'warnings' => [], 'errors' => []];
        $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, false);

        $hi = null;
        foreach ($rows as $i => $row) {
            $cells = array_map(fn ($c) => mb_strtolower(trim((string) $c)), $row);
            if (in_array('nama', $cells, true) && in_array('nik', $cells, true)) {
                $hi = $i;
                break;
            }
        }
        if ($hi === null) {
            throw new RuntimeException('Baris judul (kolom Nama dan NIK) tidak ditemukan. Pastikan ini file "Daftar Guru" dari Dapodik.');
        }

        $col = [];
        foreach ($rows[$hi] as $idx => $title) {
            $col[mb_strtolower(trim((string) $title))] ??= $idx;
        }
        $get = fn (array $row, string $name): ?string => isset($col[$name]) && trim((string) ($row[$col[$name]] ?? '')) !== '' ? trim((string) $row[$col[$name]]) : null;

        DB::beginTransaction();

        try {
            foreach (array_slice($rows, $hi + 1, null, true) as $i => $row) {
                $name = $get($row, 'nama');
                if ($name === null) {
                    continue;
                }
                $line = $i + 1;
                $nik = preg_replace('/\D+/', '', (string) $get($row, 'nik')) ?: null;
                $email = ($e = $get($row, 'email')) ? mb_strtolower($e) : null;
                $nuptk = $get($row, 'nuptk');

                if ($nik === null && $email === null && $nuptk === null) {
                    $r['failed']++;
                    $r['errors'][] = "Baris {$line} ({$name}): tidak ada NIK, email, maupun NUPTK.";

                    continue;
                }

                $teacher = ($nik ? Teacher::query()->where('nik_hash', Teacher::hashNik($nik))->first() : null)
                    ?? ($email ? Teacher::query()->where('email', $email)->first() : null)
                    ?? ($nuptk ? Teacher::query()->where('nuptk', $nuptk)->first() : null);

                $gender = strtoupper((string) $get($row, 'jk'));
                $birth = $get($row, 'tanggal lahir');
                $values = array_filter([
                    'gender' => in_array($gender, ['L', 'P'], true) ? $gender : null,
                    'nik' => $nik,
                    'nuptk' => $nuptk,
                    'nip' => $get($row, 'nip'),
                    'email' => $email,
                    'phone' => $get($row, 'hp') ?? $get($row, 'telepon'),
                    'birth_place' => ($bp = $get($row, 'tempat lahir')) ? $this->titleCase($bp) : null,
                    'birth_date' => $birth && strtotime($birth) ? date('Y-m-d', strtotime($birth)) : null,
                    'employment_status' => $get($row, 'status kepegawaian'),
                    'additional_duty' => $get($row, 'tugas tambahan'),
                ], fn ($v) => $v !== null);

                try {
                    if ($teacher) {
                        $teacher->update($values);
                        $r['updated']++;
                    } else {
                        Teacher::query()->create($values + ['name' => $this->titleCase($name), 'status' => 'aktif']);
                        $r['created']++;
                    }
                } catch (\Throwable $e) {
                    $r['failed']++;
                    $r['errors'][] = "Baris {$line} ({$name}): ".$e->getMessage();
                }
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $dryRun ? DB::rollBack() : DB::commit();

        return $r;
    }

    /** Nama tertulis HURUF BESAR semua dirapikan menjadi Huruf Awal Besar; selain itu dibiarkan. */
    private function titleCase(string $s): string
    {
        return $s === mb_strtoupper($s) ? mb_convert_case(mb_strtolower($s), MB_CASE_TITLE, 'UTF-8') : $s;
    }
}
