<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\LogsMasterData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Major extends Model
{
    use HasPublicUuid, LogsMasterData;

    protected $fillable = ['code', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class);
    }
    public function deletionBlockedReason(): ?string
    {
        return $this->classrooms()->withTrashed()->exists()
            ? 'Masih dipakai oleh kelas. Nonaktifkan saja jika sudah tidak digunakan.'
            : null;
    }

    protected static function booted(): void
    {
        static::deleting(fn(self $major): bool => $major->deletionBlockedReason() === null);
    }
}
