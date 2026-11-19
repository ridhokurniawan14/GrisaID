<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\LogsMasterData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class AcademicYear extends Model
{
    use HasPublicUuid, LogsMasterData;

    protected $fillable = ['code', 'name', 'start_date', 'end_date', 'is_active'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function current(): ?self
    {
        return static::query()->active()->first();
    }

    /** Tahun awal, mis. 2025 untuk "2025/2026". */
    public function startYear(): int
    {
        return (int) explode('/', $this->code)[0];
    }

    /** Aktifkan tahun ajaran ini dan nonaktifkan yang lain secara atomik. */
    public function activate(): void
    {
        DB::transaction(function () {
            static::query()->where('is_active', true)->whereKeyNot($this->getKey())->update(['is_active' => false]);
            $this->forceFill(['is_active' => true])->save();
        });
    }
    /** Alasan tahun ajaran tidak boleh dihapus (null = boleh). */
    public function deletionBlockedReason(): ?string
    {
        return match (true) {
            $this->is_active => 'Tahun ajaran aktif tidak bisa dihapus.',
            $this->classrooms()->withTrashed()->exists() => 'Masih memiliki kelas. Hapus atau pindahkan kelasnya dulu.',
            default => null,
        };
    }

    protected static function booted(): void
    {
        static::deleting(fn(self $year): bool => $year->deletionBlockedReason() === null);
    }
}
