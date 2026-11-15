<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Classroom */
class ClassroomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'grade' => $this->grade,
            'group_no' => $this->group_no,
            'major' => $this->whenLoaded('major', fn() => [
                'code' => $this->major->code,
                'name' => $this->major->name,
            ]),
            'academic_year' => $this->whenLoaded('academicYear', fn() => $this->academicYear->code),
            'students_count' => $this->whenCounted('enrollments'),
        ];
    }
}
