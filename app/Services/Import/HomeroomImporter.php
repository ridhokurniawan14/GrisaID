<?php

namespace App\Services\Import;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Teacher;
use App\Models\User;
use App\Services\HomeroomService;
use DomainException;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Impor "Rombongan Belajar" Dapodik untuk SATU tahun ajaran: kolom Nama Rombel dan Guru/Wali Kelas.
 * Kelas dicari di tahun ajaran itu (tidak dibuat otomatis: impor siswa dulu). Guru dicocokkan lewat nama.
 * Nama ganda / tidak ketemu dilaporkan, tidak ditebak. Aman diulang. Mode simulasi = rollback.
 */
final class HomeroomImporter
{
    public function __construct(private readonly HomeroomService $service)
    {
    }

    /** @return array{baru: int, sama: int, diganti: int, gagal: int, warnings: list<string>} */
    public function import(string $path, AcademicYear $year, bool $dryRun = true, ?User $by = null): array
    {
        $r = ['baru' => 0, 'sama' => 0, 'diganti' => 0, 'gagal' => 0, 'warnings' => []];
        [$rows, $iClass, $iTeacher] = $this->locate($path);

        $classes = Classroom::query()->where('academic_year_id', $year->id)->get()->keyBy(fn (Classroom $c) => mb_strtolower(trim($c->name)));
        $teachers = Teacher::query()->get()->groupBy(fn (Teacher $t) => Teacher::nameKey($t->name));

        DB::beginTransaction();

        try {
            foreach (array_slice($rows, 1) as $row) {
                $className = trim((string) ($row[$iClass] ?? ''));
                $teacherName = trim((string) ($row[$iTeacher] ?? ''));
                if ($className === '') {
                    continue;
                }

                $class = $classes->get(mb_strtolower($className));
                if ($class === null) {
                    $r['gagal']++;
                    $r['warnings'][] = "Kelas {$className} belum ada di {$year->name} (impor siswa dulu).";

                    continue;
                }
                if ($teacherName === '') {
                    $r['warnings'][] = "{$className}: wali kelas kosong di file.";

                    continue;
                }

                $found = $teachers->get(Teacher::nameKey($teacherName));
                if ($found === null || $found->count() !== 1) {
                    $r['gagal']++;
                    $r['warnings'][] = $found === null ? "{$className}: guru \"{$teacherName}\" tidak ada di data guru (impor Daftar Guru dulu)." : "{$className}: nama \"{$teacherName}\" cocok dengan lebih dari satu guru, atur manual.";

                    continue;
                }

                try {
                    $r[$this->service->assign($class, $found->first(), $by)]++;
                } catch (DomainException $e) {
                    $r['gagal']++;
                    $r['warnings'][] = "{$className}: ".$e->getMessage();
                }
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $dryRun ? DB::rollBack() : DB::commit();

        return $r;
    }

    /**
     * Cari lembar data (lewati "Petunjuk") dan kolom wali kelas. Nama kolom yang dikenali:
     * Nama Rombel / Rombel / Kelas  dan  Guru/Wali Kelas / Wali Kelas / Guru.
     *
     * @return array{0: list<list<mixed>>, 1: int, 2: int}
     */
    private function locate(string $path): array
    {
        foreach (IOFactory::load($path)->getAllSheets() as $sheet) {
            if (in_array(mb_strtolower(trim($sheet->getTitle())), ['petunjuk', 'panduan', 'catatan', 'readme'], true)) {
                continue;
            }

            $rows = $sheet->toArray(null, true, true, false);
            $head = array_map(fn ($c) => mb_strtolower(trim((string) $c)), $rows[0] ?? []);
            $class = $this->indexOf($head, ['nama rombel', 'rombel', 'kelas']);
            $teacher = $this->indexOf($head, ['guru/wali kelas', 'wali kelas', 'guru']);

            if ($class !== null && $teacher !== null) {
                return [$rows, $class, $teacher];
            }
        }

        throw new RuntimeException('Kolom "Nama Rombel" dan "Guru/Wali Kelas" tidak ditemukan di baris pertama. Gunakan template dari tombol "Unduh template Excel".');
    }

    /** @param list<string> $head @param list<string> $names */
    private function indexOf(array $head, array $names): ?int
    {
        foreach ($names as $n) {
            $i = array_search($n, $head, true);
            if ($i !== false) {
                return $i;
            }
        }

        return null;
    }
}