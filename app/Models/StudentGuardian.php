<?php

namespace App\Models;

use App\Enums\GuardianRelation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentGuardian extends Model
{
    protected $fillable = ['student_id', 'relation', 'name', 'occupation', 'whatsapp', 'is_primary_contact'];

    protected function casts(): array
    {
        return [
            'relation' => GuardianRelation::class,
            'is_primary_contact' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
