<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Mencatat siapa mengubah apa (nilai lama & baru) pada data master.
 * Nama log otomatis dari nama model: Student => "student", StudentGuardian => "student-guardian".
 */
trait LogsMasterData
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName(Str::kebab(class_basename($this)))
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
