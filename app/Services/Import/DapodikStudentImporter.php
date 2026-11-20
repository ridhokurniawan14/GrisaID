<?php

namespace App\Services\Import;

use App\Enums\EnrollmentStatus;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Major;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentGuardian;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Mengimpor Excel "Daftar Peserta Didik" Dapodik ke GrisaID untuk SATU tahun ajaran. Idempotent.
 *
 *  - Siswa dicari lewat NISN (cadangan: NIPD), lalu dibuat atau diperbarui
 *  - Kelas dibuat otomatis per tahun ajaran; kelas siswa dicatat di student_enrollments
 *  - Nomor WA orang tua & penanda kontak utama TIDAK pernah ditimpa (Dapodik tidak punya datanya)
 *  - Nilai kosong dari Dapodik tidak menghapus data yang sudah ada
 *  - Siswa AKTIF yang tidak ada di file dibagi dua:
 *      * "lulusan": tidak punya kelas di tahun ini dan di tahun ajaran sebelumnya duduk di kelas XII
 *      * "lainnya": mutasi / mengundurkan diri / dsb.
 *    Keduanya hanya dilaporkan, kecuali diminta ditandai. Penandaan "lainnya" dibatalkan otomatis
 *    bila terlalu banyak (file kemungkinan tidak lengkap)
 *  - Mode dry-run: semua dijalankan di dalam transaksi lalu di-rollback
 */
final class DapodikStudentImporter
{
    /** Batas penandaan "lainnya": lebih dari max(20, 20% populasi) dianggap file tidak lengkap. */
    private const MAX_MISSING_RATIO = 0.2;

    private const MIN_MISSING_ALLOWANCE = 20;

    public function __construct(private readonly DapodikStudentParser $parser) {}

    /**
     * @return array{created: int, updated: int, skipped: int, failed: int, classrooms_created: int, graduates_count: int, graduates: list<string>, graduates_marked: int, missing_count: int, missing: list<string>, missing_marked: int, warnings: list<string>, errors: list<string>}
     */
    public function import(
        string $path,
        AcademicYear $year,
        bool $dryRun = true,
        ?StudentStatus $statusForGraduates = null,
        ?StudentStatus $statusForOthers = null,
    ): array {
        $report = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'classrooms_created' => 0,
            'graduates_count' => 0,
            'graduates' => [],
            'graduates_marked' => 0,
            'missing_count' => 0,
            'missing' => [],
            'missing_marked' => 0,
            'warnings' => [],
            'errors' => [],
        ];

        $sheets = $this->readSheets($path);
        $majors = Major::pluck('id', 'code')->all(); // ['KUL' => 1, ...]
        $seen = [];
        $seenStudentIds = [];

        DB::beginTransaction();

        try {
            foreach ($sheets as $sheetName => $rows) {
                foreach ($rows as $index => $row) {
                    if (! $this->parser->isDataRow($row)) {
                        continue;
                    }

                    $where = $sheetName . ' baris ' . ($index + 1);
                    $parsed = $this->parser->parse($row);

                    foreach ($parsed['warnings'] as $warning) {
                        $report['warnings'][] = "{$where}: {$warning}";
                    }

                    if ($parsed['errors'] !== []) {
                        $report['failed']++;
                        foreach ($parsed['errors'] as $error) {
                            $report['errors'][] = "{$where}: {$error}";
                        }

                        continue;
                    }

                    $data = $parsed['data'];
                    $key = $data['nisn'] ?? 'nis:' . $data['nis'];

                    if (isset($seen[$key])) {
                        $report['skipped']++; // siswa yang sama muncul lagi di sheet lain

                        continue;
                    }
                    $seen[$key] = true;

                    try {
                        // Transaksi bersarang = savepoint: satu baris gagal tidak merusak yang lain.
                        $result = DB::transaction(fn() => $this->upsert($data, $year, $majors));
                        $report[$result['status']]++;
                        $report['classrooms_created'] += $result['classroom_created'] ? 1 : 0;
                        $seenStudentIds[] = $result['student_id'];
                    } catch (\Throwable $e) {
                        $report['failed']++;
                        $report['errors'][] = "{$where}: " . $e->getMessage();
                    }
                }
            }

            $this->handleMissing($report, $year, $seenStudentIds, $statusForGraduates, $statusForOthers);

            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        return $report;
    }

    /**
     * Siswa AKTIF yang tidak ditemukan di file, dipisah menjadi lulusan dan lainnya.
     *
     * @param  array<string, mixed>  $report
     * @param  list<int>  $seenStudentIds
     */
    private function handleMissing(
        array &$report,
        AcademicYear $year,
        array $seenStudentIds,
        ?StudentStatus $statusForGraduates,
        ?StudentStatus $statusForOthers,
    ): void {
        $previousYear = AcademicYear::query()
            ->where('start_date', '<', $year->start_date)
            ->orderByDesc('start_date')
            ->first();

        $yearIds = array_values(array_filter([$year->id, $previousYear?->id]));

        $candidates = Student::query()
            ->where('status', StudentStatus::Active->value)
            ->whereHas('enrollments', fn($enrollment) => $enrollment->whereIn('academic_year_id', $yearIds))
            ->when($seenStudentIds !== [], fn($query) => $query->whereNotIn('id', $seenStudentIds))
            ->with(['enrollments' => fn($enrollment) => $enrollment->whereIn('academic_year_id', $yearIds)->with('classroom')])
            ->orderBy('full_name')
            ->get();

        $graduates = [];
        $others = [];

        foreach ($candidates as $student) {
            $thisYear = $student->enrollments->firstWhere('academic_year_id', $year->id);
            $lastYear = $previousYear === null ? null : $student->enrollments->firstWhere('academic_year_id', $previousYear->id);

            // Lulusan: tidak punya kelas di tahun ini, dan tahun sebelumnya duduk di kelas XII.
            if ($thisYear === null && $lastYear?->classroom?->grade === 12) {
                $graduates[] = $student;
            } else {
                $others[] = $student;
            }
        }

        $report['graduates_count'] = count($graduates);
        $report['graduates'] = $this->names($graduates);
        $report['missing_count'] = count($others);
        $report['missing'] = $this->names($others);

        if ($statusForGraduates !== null) {
            $report['graduates_marked'] = $this->markStudents($graduates, $statusForGraduates, $year);
        }

        if ($statusForOthers === null || $others === []) {
            return;
        }

        $population = count($seenStudentIds) + count($others);
        $limit = max(self::MIN_MISSING_ALLOWANCE, (int) ceil($population * self::MAX_MISSING_RATIO));

        if (count($others) > $limit) {
            $report['errors'][] = sprintf(
                'Penandaan status siswa lain dibatalkan: %d dari %d siswa tidak ada di file (terlalu banyak, file kemungkinan tidak lengkap). Tidak ada status yang diubah.',
                count($others),
                $population,
            );

            return;
        }

        $report['missing_marked'] = $this->markStudents($others, $statusForOthers, $year);
    }

    /**
     * @param  list<Student>  $students
     */
    private function markStudents(array $students, StudentStatus $status, AcademicYear $year): int
    {
        $enrollmentStatus = EnrollmentStatus::from($status->value);

        foreach ($students as $student) {
            $student->update(['status' => $status->value]);

            $enrollment = $student->enrollments->firstWhere('academic_year_id', $year->id) ?? $student->enrollments->first();
            $enrollment?->update(['status' => $enrollmentStatus->value]);
        }

        return count($students);
    }

    /**
     * @param  list<Student>  $students
     * @return list<string>
     */
    private function names(array $students): array
    {
        return array_map(
            fn(Student $student): string => trim($student->full_name . ' (' . ($student->nisn ?? $student->nis ?? '-') . ')'),
            array_slice($students, 0, 50),
        );
    }

    /**
     * @param  array<string, mixed>  $d
     * @param  array<string, int>  $majors
     * @return array{status: 'created'|'updated', classroom_created: bool, student_id: int}
     */
    private function upsert(array $d, AcademicYear $year, array $majors): array
    {
        $rombel = $d['rombel'];

        $majorId = $majors[$rombel['major_code']]
            ?? throw new RuntimeException("Jurusan {$rombel['major_code']} belum ada di database (jalankan db:seed)");

        $classroom = Classroom::firstOrCreate(
            ['academic_year_id' => $year->id, 'name' => $rombel['name']],
            ['major_id' => $majorId, 'grade' => $rombel['grade'], 'group_no' => $rombel['group_no']],
        );

        $attributes = [
            'nisn' => $d['nisn'],
            'nis' => $d['nis'],
            'full_name' => $d['full_name'],
            'gender' => $d['gender'],
            'birth_place' => $d['birth_place'],
            'birth_date' => $d['birth_date'],
            'address' => $d['address'],
            'phone' => $d['phone'],
        ];

        $student = null;
        if ($d['nisn'] !== null) {
            $student = Student::withTrashed()->where('nisn', $d['nisn'])->first();
        }
        if ($student === null && $d['nis'] !== null) {
            $student = Student::withTrashed()->where('nis', $d['nis'])->first();
        }

        if ($student !== null) {
            if ($student->trashed()) {
                $student->restore();
            }
            // Nilai kosong dari Dapodik tidak boleh menghapus data yang sudah ada.
            $student->fill(array_filter($attributes, fn($v) => $v !== null))->save();
            $status = 'updated';
        } else {
            // Angkatan = tahun mulai tahun ajaran dikurangi (tingkat - 10). Contoh: TA 2026/2027, kelas X => 2026.
            $entryYear = $year->startYear() - ($rombel['grade'] - 10);

            $student = Student::create($attributes + [
                'entry_year' => $entryYear,
                'status' => StudentStatus::Active->value,
            ]);
            $status = 'created';
        }

        StudentEnrollment::updateOrCreate(
            ['student_id' => $student->id, 'academic_year_id' => $year->id],
            ['classroom_id' => $classroom->id, 'status' => EnrollmentStatus::Active->value],
        );

        foreach ($d['guardians'] as $relation => $guardianData) {
            $guardian = StudentGuardian::firstOrNew(['student_id' => $student->id, 'relation' => $relation]);
            $guardian->name = $guardianData['name'];
            $guardian->occupation = $guardianData['occupation'] ?? $guardian->occupation;
            $guardian->save(); // whatsapp & is_primary_contact sengaja tidak disentuh
        }

        return ['status' => $status, 'classroom_created' => $classroom->wasRecentlyCreated, 'student_id' => $student->id];
    }

    /** @return array<string, list<list<mixed>>> nama sheet => baris mentah */
    private function readSheets(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);

        $sheets = [];
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            // (nilai kosong, hitung formula, format tampilan, kunci sel) => nilai mentah apa adanya
            $sheets[$sheet->getTitle()] = $sheet->toArray(null, false, false, false);
        }

        $spreadsheet->disconnectWorksheets();

        return $sheets;
    }
}
