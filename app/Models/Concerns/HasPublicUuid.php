<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * Model tetap memakai id angka secara internal, tetapi kolom `uuid` dibuat otomatis
 * dan dipakai sebagai ID publik (route binding & API), mis. /api/v1/students/{uuid}.
 */
trait HasPublicUuid
{
    use HasUuids;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
