<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Teacher extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['nik' => 'encrypted', 'birth_date' => 'date'];
    }

    protected static function booted(): void
    {
        static::saving(function (Teacher $t): void {
            $t->nik_hash = filled($t->nik) ? self::hashNik((string) $t->nik) : null;
        });
    }

    public static function hashNik(string $nik): string
    {
        return hash('sha256', preg_replace('/\D+/', '', $nik));
    }

    /** "Drs. Nama, M.Pd." */
    public function getFullNameAttribute(): string
    {
        return trim(trim(($this->front_title ? $this->front_title.' ' : '').$this->name).($this->back_title ? ', '.$this->back_title : ''));
    }

    /** Kunci pencocokan nama (huruf kecil, tanpa tanda baca/gelar umum). */
    public static function nameKey(string $name): string
    {
        $name = mb_strtolower($name);
        $name = preg_replace('/\b(drs|dra|dr|hj|h|ir|prof)\b\.?/u', ' ', $name);
        $name = preg_replace('/,.*$/u', '', $name);           // buang gelar belakang setelah koma
        $name = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name);

        return trim(preg_replace('/\s+/', ' ', $name));
    }

    public function homerooms(): HasMany
    {
        return $this->hasMany(HomeroomAssignment::class);
    }
}
