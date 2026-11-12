<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Classroom extends Model
{
    use HasPublicUuid, SoftDeletes;

    protected $fillable = ['academic_year_id', 'major_id', 'grade', 'name', 'group_no'];

    protected function casts(): array
    {
        return ['grade' => 'integer', 'group_no' => 'integer'];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function major(): BelongsTo
    {
        return $this->belongsTo(Major::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    public function scopeInActiveYear(Builder $query): Builder
    {
        return $query->whereHas('academicYear', fn(Builder $q) => $q->where('is_active', true));
    }
}
