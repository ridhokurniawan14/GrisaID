<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\HomeroomAssignment;
use App\Models\Teacher;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Penetapan wali kelas. Satu kelas = satu wali; satu guru = satu kelas per tahun ajaran. */
final class HomeroomService
{
    /** @return 'baru'|'sama'|'diganti' */
    public function assign(Classroom $classroom, Teacher $teacher, ?User $by = null): string
    {
        return DB::transaction(function () use ($classroom, $teacher, $by): string {
            $other = DB::table('homeroom_assignments as h')
                ->join('classrooms as c', 'c.id', '=', 'h.classroom_id')
                ->where('h.teacher_id', $teacher->id)
                ->where('c.academic_year_id', $classroom->academic_year_id)
                ->where('h.classroom_id', '!=', $classroom->id)
                ->value('c.name');

            if ($other !== null) {
                throw new DomainException("{$teacher->full_name} sudah menjadi wali kelas {$other} di tahun ajaran yang sama.");
            }

            $current = HomeroomAssignment::query()->where('classroom_id', $classroom->id)->first();

            if ($current?->teacher_id === $teacher->id) {
                return 'sama';
            }

            $old = $current?->teacher?->full_name;
            HomeroomAssignment::query()->updateOrCreate(['classroom_id' => $classroom->id], ['teacher_id' => $teacher->id, 'assigned_by' => $by?->id]);

            activity('wali-kelas')->performedOn($classroom)->causedBy($by)
                ->withProperties(['guru' => $teacher->full_name, 'sebelumnya' => $old])
                ->log($old ? "Wali kelas {$classroom->name} diganti" : "Wali kelas {$classroom->name} ditetapkan");

            return $current ? 'diganti' : 'baru';
        });
    }

    public function clear(Classroom $classroom, ?User $by = null): void
    {
        $current = HomeroomAssignment::query()->where('classroom_id', $classroom->id)->with('teacher')->first();

        if ($current) {
            $current->delete();
            activity('wali-kelas')->performedOn($classroom)->causedBy($by)->withProperties(['guru' => $current->teacher?->full_name])->log("Wali kelas {$classroom->name} dikosongkan");
        }
    }

    /**
     * Wali kelas dibawa sampai kelas 3: salin wali dari kelas tahun lalu ke kelas tujuannya (X PH 1 → XI PH 1)
     * hanya bila kelas baru belum punya wali. Bisa diganti manual sesudahnya.
     *
     * @return array{disalin: int, dilewati: int, warnings: list<string>}
     */
    public function carryForward(AcademicYear $from, AcademicYear $to, ?User $by = null): array
    {
        $r = ['disalin' => 0, 'dilewati' => 0, 'warnings' => []];
        $targets = Classroom::query()->where('academic_year_id', $to->id)->get()->keyBy(fn (Classroom $c) => mb_strtolower($c->name));

        foreach (Classroom::query()->where('academic_year_id', $from->id)->get() as $old) {
            $next = StudentPromotion::nextClassName($old->name, (int) $old->grade);
            $assignment = HomeroomAssignment::query()->where('classroom_id', $old->id)->with('teacher')->first();
            $target = $next ? $targets->get(mb_strtolower($next)) : null;

            if ($assignment === null || $target === null) {
                continue;
            }

            if (HomeroomAssignment::query()->where('classroom_id', $target->id)->exists()) {
                $r['dilewati']++;

                continue;
            }

            try {
                $this->assign($target, $assignment->teacher, $by);
                $r['disalin']++;
            } catch (DomainException $e) {
                $r['dilewati']++;
                $r['warnings'][] = $e->getMessage();
            }
        }

        return $r;
    }
}
