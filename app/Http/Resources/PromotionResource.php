<?php

namespace App\Http\Resources;

use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Promotion */
class PromotionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description ?? '',
            'discountType' => $this->discount_type,
            'value' => $this->value,
            'startDate' => $this->start_date->toDateString(),
            'endDate' => $this->end_date->toDateString(),
            'conditions' => $this->conditions,
            'active' => $this->is_active,
            'createdAt' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
