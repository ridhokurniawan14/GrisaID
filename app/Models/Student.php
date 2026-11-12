<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasPublicUuid, SoftDeletes;

    protected $fillable = [
        'nisn',
        'nis',
        'full_name',
        'gender',
        'birth_place',
        'birth_date',
        'address',
        'phone',
        'entry_year',
        'status',
        'status_changed_at',
    ];

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'status' => StudentStatus::class,
            'birth_date' => 'date',
            'entry_year' => 'integer',
            'status_changed_at' => 'datetime',
        ];
    }

    public function guardians(): HasMany
    {
        return $this->hasMany(StudentGuardian::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    /** Enrollment pada tahun ajaran AKTIF (= kelas saat ini). Aman di-eager-load. */
    public function currentEnrollment(): HasOne
    {
        return $this->hasOne(StudentEnrollment::class)
            ->whereHas('academicYear', fn(Builder $q) => $q->where('is_active', true));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', StudentStatus::Active->value);
    }

    public function scopeEntryYear(Builder $query, int $year): Builder
    {
        return $query->where('entry_year', $year);
    }

    /** Filter kelas (id angka atau nama rombel) pada tahun ajaran aktif. */
    public function scopeInClassroom(Builder $query, int|string $classroom): Builder
    {
        return $query->whereHas('currentEnrollment.classroom', function (Builder $q) use ($classroom) {
            is_numeric($classroom)
                ? $q->whereKey((int) $classroom)
                : $q->where('name', $classroom);
        });
    }
}
