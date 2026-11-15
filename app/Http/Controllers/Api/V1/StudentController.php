<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StudentResource;
use App\Models\AcademicYear;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    private const ENROLLMENT_FILTERS = ['academic_year', 'classroom', 'grade', 'major'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'nisn' => ['sometimes', 'string', 'size:10'],
            'nis' => ['sometimes', 'string', 'max:30'],
            'search' => ['sometimes', 'string', 'min:2', 'max:100'],
            'status' => ['sometimes', Rule::enum(StudentStatus::class)],
            'entry_year' => ['sometimes', 'integer', 'between:2000,2100'],
            'academic_year' => ['sometimes', 'string', 'exists:academic_years,code'],
            'classroom' => ['sometimes', 'string', 'max:50'], // nama rombel (X KULINER 1) atau uuid
            'grade' => ['sometimes', 'integer', 'in:10,11,12'],
            'major' => ['sometimes', 'string', 'exists:majors,code'],
            'updated_since' => ['sometimes', 'date'],
            'with_trashed' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'between:1,200'],
        ]);

        $year = $this->resolveYear($filters['academic_year'] ?? null);

        $query = Student::query()
            ->with($this->relations($year))
            ->orderBy('id');

        if (isset($filters['nisn'])) {
            $query->where('nisn', $filters['nisn']);
        }
        if (isset($filters['nis'])) {
            $query->where('nis', $filters['nis']);
        }
        if (isset($filters['search'])) {
            $term = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['search']);
            $query->where('full_name', 'ilike', "%{$term}%");
        }
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['entry_year'])) {
            $query->where('entry_year', (int) $filters['entry_year']);
        }
        if (isset($filters['updated_since'])) {
            // ">=" agar tidak ada data yang terlewat; konsumen cukup deduplikasi berdasarkan id.
            $query->where('updated_at', '>=', Carbon::parse($filters['updated_since']));
        }
        if ($request->boolean('with_trashed')) {
            $query->withTrashed(); // untuk sinkronisasi: siswa yang dihapus ikut muncul dengan deleted_at
        }

        if (array_intersect_key($filters, array_flip(self::ENROLLMENT_FILTERS)) !== []) {
            $query->whereHas('enrollments', function ($enrollment) use ($year, $filters) {
                $enrollment->where('academic_year_id', $year?->id ?? 0);

                $enrollment->whereHas('classroom', function ($classroom) use ($filters) {
                    if (isset($filters['classroom'])) {
                        Str::isUuid($filters['classroom'])
                            ? $classroom->where('uuid', $filters['classroom'])
                            : $classroom->where('name', Str::upper($filters['classroom']));
                    }
                    if (isset($filters['grade'])) {
                        $classroom->where('grade', (int) $filters['grade']);
                    }
                    if (isset($filters['major'])) {
                        $classroom->whereHas('major', fn($major) => $major->where('code', $filters['major']));
                    }
                });
            });
        }

        return StudentResource::collection(
            $query->paginate((int) ($filters['per_page'] ?? 50))->withQueryString()
        );
    }

    public function show(Request $request, string $identifier): StudentResource
    {
        $request->validate(['academic_year' => ['sometimes', 'string', 'exists:academic_years,code']]);

        $year = $this->resolveYear($request->query('academic_year'));

        $query = Student::query()->with([...$this->relations($year), 'guardians']);

        if (Str::isUuid($identifier)) {
            $query->where('uuid', $identifier);
        } elseif (preg_match('/^\d{10}$/', $identifier) === 1) {
            $query->where('nisn', $identifier);
        } else {
            abort(404, 'Siswa tidak ditemukan.');
        }

        return new StudentResource($query->firstOrFail());
    }

    private function resolveYear(?string $code): ?AcademicYear
    {
        return $code !== null
            ? AcademicYear::where('code', $code)->first()
            : AcademicYear::current();
    }

    /** Hanya muat enrollment pada tahun ajaran yang dipilih, supaya kelas yang tampil konsisten dengan filter. */
    private function relations(?AcademicYear $year): array
    {
        return [
            'enrollments' => fn($query) => $query
                ->where('academic_year_id', $year?->id ?? 0)
                ->with('classroom.major'),
        ];
    }
}
