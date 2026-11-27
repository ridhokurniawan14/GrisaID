<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\StudentEnrollment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Naik kelas massal: kelas X → XI, XI → XII, dan (opsional) kelas XII → lulus (alumni).
 *
 * Kelas tujuan dibuat otomatis di tahun ajaran tujuan dengan nama yang sama selain tingkatnya
 * (X PH 1 → XI PH 1). Siswa yang sudah punya kelas di tahun tujuan TIDAK diubah, jadi aman diulang
 * dan tidak menimpa pengaturan manual ("Atur kelas"). Siswa tinggal kelas cukup diatur manual setelahnya.
 */
class StudentPromotion
{
    private const ROMAN = [10 => 'X', 11 => 'XI', 12 => 'XII'];

    /** Nama kelas tujuan, atau null bila nama tidak berawalan angka romawi yang sesuai tingkatnya. */
    public static function nextClassName(string $name, int $grade): ?string
    {
        $roman = self::ROMAN[$grade] ?? null;
        $next = self::ROMAN[$grade + 1] ?? null;

        if ($roman === null || $next === null) {
            return null;
        }

        if (! preg_match('/^'.$roman.'(?![A-Za-z])[\s\-_.]*(.*)$/u', trim($name), $m)) {
            return null;
        }

        return trim($next.' '.$m[1]);
    }

    /**
     * @return array{naik_10: int, naik_11: int, lulus: int, sudah_punya_kelas: int, kelas12_dibiarkan: int, kelas_baru: int, warnings: list<string>, simulasi: bool}
     */
    public function run(AcademicYear $from, AcademicYear $to, bool $graduate, bool $commit): array
    {
        if ($from->id === $to->id) {
            throw new RuntimeException('Tahun ajaran asal dan tujuan tidak boleh sama.');
        }

        if ($to->start_date <= $from->start_date) {
            throw new RuntimeException('Tahun ajaran tujuan harus lebih baru daripada tahun ajaran asal.');
        }

        $report = ['naik_10' => 0, 'naik_11' => 0, 'lulus' => 0, 'sudah_punya_kelas' => 0, 'kelas12_dibiarkan' => 0, 'kelas_baru' => 0, 'warnings' => [], 'simulasi' => ! $commit];

        DB::beginTransaction();

        try {
            StudentEnrollment::query()
                ->where('academic_year_id', $from->id)
                ->where('status', EnrollmentStatus::Active->value)
                ->whereHas('student', fn ($q) => $q->where('status', StudentStatus::Active->value))
                ->with(['student', 'classroom'])
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($to, $graduate, &$report): void {
                    foreach ($rows as $enrollment) {
                        $this->promoteOne($enrollment, $to, $graduate, $report);
                    }
                });

            $commit ? DB::commit() : DB::rollBack();
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        return $report;
    }

    /** @param array<string, mixed> $report */
    private function promoteOne(StudentEnrollment $enrollment, AcademicYear $to, bool $graduate, array &$report): void
    {
        $student = $enrollment->student;
        $classroom = $enrollment->classroom;
        $grade = (int) $classroom?->grade;

        if ($grade === 12) {
            if (! $graduate) {
                $report['kelas12_dibiarkan']++;

                return;
            }

            $student->update(['status' => StudentStatus::from('lulus')->value]);
            $enrollment->update(['status' => EnrollmentStatus::from('lulus')->value]);
            $report['lulus']++;

            return;
        }

        if (! in_array($grade, [10, 11], true)) {
            $this->warn($report, "{$student->full_name}: tingkat kelas tidak dikenali.");

            return;
        }

        $newName = self::nextClassName((string) $classroom->name, $grade);

        if ($newName === null) {
            $this->warn($report, "{$student->full_name}: nama kelas \"{$classroom->name}\" tidak bisa diubah otomatis.");

            return;
        }

        if (StudentEnrollment::query()->where('student_id', $student->id)->where('academic_year_id', $to->id)->exists()) {
            $report['sudah_punya_kelas']++;

            return;
        }

        $target = Classroom::firstOrCreate(
            ['academic_year_id' => $to->id, 'name' => $newName],
            ['major_id' => $classroom->major_id, 'grade' => $grade + 1, 'group_no' => $classroom->group_no],
        );

        if ($target->wasRecentlyCreated) {
            $report['kelas_baru']++;
        }

        StudentEnrollment::create(['student_id' => $student->id, 'academic_year_id' => $to->id, 'classroom_id' => $target->id, 'status' => EnrollmentStatus::Active->value]);
        $report['naik_'.$grade]++;
    }

    /** @param array<string, mixed> $report */
    private function warn(array &$report, string $message): void
    {
        if (count($report['warnings']) < 30) {
            $report['warnings'][] = $message;
        }
    }
}
