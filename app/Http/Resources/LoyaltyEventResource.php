<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** `LoyaltyEvent` (back-office) / événement de fidélité (mobile). @mixin \App\Models\LoyaltyTransaction */
class LoyaltyEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clientId' => $this->customer_id,
            'clientName' => $this->customer?->fullName() ?? '',
            'date' => $this->occurred_on->toDateString(),
            'label' => $this->label,
            'type' => $this->type,
        ];
    }
}
