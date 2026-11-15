<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sengaja TIDAK mengekspos alamat, tanggal lahir, dan data sensitif Dapodik.
 * Tambahkan field hanya bila aplikasi konsumen benar-benar membutuhkannya.
 *
 * @mixin \App\Models\Student
 */
class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'nisn' => $this->nisn,
            'nis' => $this->nis,
            'name' => $this->full_name,
            'gender' => $this->gender->value,
            'phone' => $this->phone,
            'entry_year' => $this->entry_year,
            'status' => $this->status->value,

            // Kelas pada tahun ajaran yang diminta (default: aktif). null = tidak terdaftar di tahun itu.
            'classroom' => $this->whenLoaded('enrollments', function () {
                $enrollment = $this->enrollments->first();

                return $enrollment === null ? null : [
                    ...(new ClassroomResource($enrollment->classroom))->resolve(),
                    'enrollment_status' => $enrollment->status->value,
                ];
            }),

            'guardians' => GuardianResource::collection($this->whenLoaded('guardians')),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->when($this->deleted_at !== null, fn() => $this->deleted_at->toIso8601String()),
        ];
    }
}
