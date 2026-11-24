<?php

namespace App\Services\Import;

use App\Enums\StudentStatus;
use App\Models\Student;
use App\Models\StudentGuardian;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Mengimpor data ALUMNI dari Excel: satu sheet per tahun lulus (nama sheet 2020, 2021, ...),
 * kolom Nama, L/P, NIK, NISN, Tanggal Lahir, Nama Ibu, Rombel Terakhir.
 *
 *  - Siswa dicari lewat NISN. Yang belum ada dibuat dengan status Lulus, angkatan = tahun lulus - 3
 *  - Rombel Terakhir diabaikan (kolom berisi "undefined"): alumni dibuat tanpa penempatan kelas
 *  - Siswa yang sudah ada TIDAK diubah statusnya; hanya data yang masih kosong yang dilengkapi
 *  - Siswa yang masih berstatus Aktif dilewati dan dilaporkan (kemungkinan salah file)
 *  - Aman diulang. Mode simulasi: semua dijalankan di dalam transaksi lalu di-rollback
 */
final class AlumniStudentImporter
{
    /** Lama sekolah SMK (tahun) untuk memperkirakan angkatan masuk. */
    private const YEARS_OF_STUDY = 3;

    public function __construct(private readonly AlumniRowParser $parser)
    {
    }

    /**
     * @return array{created: int, updated: int, skipped: int, active_skipped: int, failed: int, by_year: array<string, array{created: int, updated: int, skipped: int}>, warnings: list<string>, errors: list<string>}
     */
    public function import(string $path, bool $dryRun = true): array
    {
        $report = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'active_skipped' => 0, 'failed' => 0, 'by_year' => [], 'warnings' => [], 'errors' => []];

        $sheets = $this->readSheets($path);
        $seen = [];

        DB::beginTransaction();

        try {
            foreach ($sheets as $title => $rows) {
                if (in_array(mb_strtolower(trim((string) $title)), ['petunjuk', 'panduan', 'catatan', 'readme'], true)) {
                    continue; // lembar petunjuk pada template
                }

                $year = $this->parser->graduationYear((string) $title, (int) date('Y'));

                if ($year === null) {
                    $report['warnings'][] = "Sheet '{$title}' dilewati: nama sheet harus berupa tahun lulus (mis. 2024).";

                    continue;
                }

                $map = $this->parser->headerMap($rows[0] ?? []);

                if ($map === null) {
                    $report['errors'][] = "Sheet {$title}: kolom Nama dan NISN tidak ditemukan di baris pertama.";

                    continue;
                }

                $report['by_year'][(string) $year] = ['created' => 0, 'updated' => 0, 'skipped' => 0];

                foreach (array_slice($rows, 1, null, true) as $index => $row) {
                    if (array_filter($row, fn ($v): bool => $v !== null && trim((string) $v) !== '') === []) {
                        continue; // baris kosong
                    }

                    $where = "{$title} baris ".($index + 1);
                    $parsed = $this->parser->parse($row, $map);

                    if ($parsed['errors'] !== []) {
                        $report['failed']++;
                        $report['errors'][] = "{$where}: ".implode('; ', $parsed['errors']);

                        continue;
                    }

                    foreach ($parsed['warnings'] as $warning) {
                        $report['warnings'][] = "{$where}: {$warning}";
                    }

                    $d = $parsed['data'];

                    if (isset($seen[$d['nisn']])) {
                        $report['skipped']++;
                        $report['by_year'][(string) $year]['skipped']++;

                        continue; // NISN yang sama muncul lagi di sheet lain
                    }

                    $seen[$d['nisn']] = true;

                    try {
                        $result = DB::transaction(fn (): string => $this->upsert($d, $year)); // savepoint per baris
                    } catch (\Throwable $e) {
                        $report['failed']++;
                        $report['errors'][] = "{$where}: ".$e->getMessage();

                        continue;
                    }

                    if ($result === 'active') {
                        $report['active_skipped']++;
                        $report['warnings'][] = "{$where}: {$d['full_name']} ({$d['nisn']}) masih berstatus Aktif di GrisaID, dilewati.";

                        continue;
                    }

                    $report[$result]++;
                    $report['by_year'][(string) $year][$result]++;
                }
            }

            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        return $report;
    }

    private ?bool $profileTable = null;

    private function hasProfileTable(): bool
    {
        return $this->profileTable ??= Schema::hasTable('student_profiles');
    }

    /** @return 'created'|'updated'|'skipped'|'active' */
    private function upsert(array $d, int $year): string
    {
        $student = Student::withTrashed()->where('nisn', $d['nisn'])->first();
        $created = false;
        $changed = false;

        if ($student === null) {
            $student = new Student();
            $student->forceFill([
                'nisn' => $d['nisn'],
                'full_name' => $d['full_name'],
                'gender' => $d['gender'],
                'birth_date' => $d['birth_date'],
                'entry_year' => $year - self::YEARS_OF_STUDY,
                'status' => StudentStatus::Graduated->value,
                'status_changed_at' => "{$year}-06-30 00:00:00",
            ])->save();
            $created = true;
        } else {
            $current = $student->status instanceof \BackedEnum ? $student->status->value : (string) $student->status;

            if ($current === StudentStatus::Active->value) {
                return 'active';
            }

            if ($student->trashed()) {
                $student->restore();
                $changed = true;
            }

            if ($student->birth_date === null && $d['birth_date'] !== null) {
                $student->forceFill(['birth_date' => $d['birth_date']])->save();
                $changed = true;
            }
        }

        // Ditulis langsung ke tabel (terenkripsi seperti cast 'encrypted' model), tanpa bergantung pada kelas model.
        if ($d['nik'] !== null && $this->hasProfileTable()) {
            $profile = DB::table('student_profiles')->where('student_id', $student->id)->first();

            if ($profile === null) {
                DB::table('student_profiles')->insert([
                    'student_id' => $student->id,
                    'nik' => Crypt::encryptString($d['nik']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $changed = true;
            } elseif (blank($profile->nik)) {
                DB::table('student_profiles')->where('id', $profile->id)->update(['nik' => Crypt::encryptString($d['nik']), 'updated_at' => now()]);
                $changed = true;
            }
        }

        if ($d['mother'] !== null) {
            $guardian = StudentGuardian::query()->firstOrNew(['student_id' => $student->id, 'relation' => 'ibu']);

            if (blank($guardian->name)) {
                $guardian->name = $d['mother'];
                $guardian->save(); // WhatsApp & kontak utama sengaja tidak disentuh
                $changed = true;
            }
        }

        return $created ? 'created' : ($changed ? 'updated' : 'skipped');
    }

    /** @return array<string, list<list<mixed>>> nama sheet => baris mentah */
    private function readSheets(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);

        $sheets = [];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $sheets[$sheet->getTitle()] = $sheet->toArray(null, false, false, false);
        }

        $spreadsheet->disconnectWorksheets();

        return $sheets;
    }
}
