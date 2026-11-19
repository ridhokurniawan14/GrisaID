<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Models\Concerns\LogsMasterData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentEnrollment extends Model
{
    use LogsMasterData;

    protected $fillable = [
        'student_id',
        'classroom_id',
        'academic_year_id',
        'status',
        'enrolled_at',
        'left_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'enrolled_at' => 'date',
            'left_at' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
