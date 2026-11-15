<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\StudentGuardian */
class GuardianResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'relation' => $this->relation->value,
            'name' => $this->name,
            'occupation' => $this->occupation,
            'whatsapp' => $this->whatsapp,
            'is_primary_contact' => $this->is_primary_contact,
        ];
    }
}
