<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/v1/me  (scope me:read) — siapa pemilik token. Dipakai GrisaKAD dan GrisaPay untuk menentukan
 * hak akses: siswa → hanya dirinya; guru → kelas yang dia walikan di tahun ajaran aktif.
 * ID siswa & kelas = kolom `uuid` (sama dengan yang dipakai API lain dan "grisaid_id" di GrisaPay).
 * "scopes" = scope yang dimiliki token ini (GrisaPay memeriksa bills:read).
 */
class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user('api');
        $year = AcademicYear::query()->where('is_active', true)->first();

        $out = [
            'id' => $user->id,
            'name' => $user->name,
            'type' => $user->account_type,
            'scopes' => array_values((array) ($user->token()?->scopes ?? [])),
            'student' => null,
            'teacher' => null,
            'classrooms' => [],
        ];

        if ($user->student_id && ($s = Student::query()->find($user->student_id))) {
            $class = $year ? DB::table('student_enrollments as e')->join('classrooms as c', 'c.id', '=', 'e.classroom_id')
                ->where('e.student_id', $s->id)->where('e.academic_year_id', $year->id)->value('c.name') : null;
            $out['student'] = ['id' => $s->uuid, 'nisn' => $s->nisn, 'name' => $s->full_name, 'class' => $class];
        }

        if ($user->teacher_id && ($t = Teacher::query()->find($user->teacher_id))) {
            $out['teacher'] = ['id' => $t->id, 'name' => $t->full_name];

            if ($year) {
                $out['classrooms'] = DB::table('homeroom_assignments as h')->join('classrooms as c', 'c.id', '=', 'h.classroom_id')
                    ->where('h.teacher_id', $t->id)->where('c.academic_year_id', $year->id)
                    ->get(['c.uuid', 'c.name'])->map(fn($c) => ['id' => $c->uuid, 'name' => $c->name])->all();
            }
        }

        return response()->json(['data' => $out]);
    }
}
