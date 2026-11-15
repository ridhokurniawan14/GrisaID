<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ClassroomResource;
use App\Models\AcademicYear;
use App\Models\Classroom;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClassroomController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'academic_year' => ['sometimes', 'string', 'exists:academic_years,code'],
            'grade' => ['sometimes', 'integer', 'in:10,11,12'],
            'major' => ['sometimes', 'string', 'exists:majors,code'],
        ]);

        $year = isset($filters['academic_year'])
            ? AcademicYear::where('code', $filters['academic_year'])->first()
            : AcademicYear::current();

        $query = Classroom::query()
            ->with(['major', 'academicYear'])
            ->withCount('enrollments')
            ->where('academic_year_id', $year?->id ?? 0)
            ->orderBy('grade')
            ->orderBy('name');

        if (isset($filters['grade'])) {
            $query->where('grade', (int) $filters['grade']);
        }
        if (isset($filters['major'])) {
            $query->whereHas('major', fn($major) => $major->where('code', $filters['major']));
        }

        return ClassroomResource::collection($query->get());
    }
}
