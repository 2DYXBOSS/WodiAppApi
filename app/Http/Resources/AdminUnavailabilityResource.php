<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** `Unavailability` (back-office) = absence sur journées entières. @mixin \App\Models\Absence */
class AdminUnavailabilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'staffId' => $this->professional_id,
            'staffName' => $this->professional?->fullName() ?? '',
            'type' => $this->type->value,
            'startDate' => $this->start_date->toDateString(),
            'endDate' => $this->end_date->toDateString(),
            'reason' => $this->reason,
            'status' => $this->status->value,
            'createdAt' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
