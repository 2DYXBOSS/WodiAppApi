<?php

namespace App\Http\Resources;

use App\Models\Service;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Service */
class AdminServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'categoryId' => $this->category_id,
            'categoryName' => $this->category?->name ?? '',
            'description' => $this->description ?? '',
            'price' => $this->price,
            'priceFrom' => $this->price_from,
            'durationMinutes' => $this->duration_minutes,
            'active' => $this->is_active,
            'image' => Media::url($this->image),
            'locations' => $this->locations(),
            'conditions' => $this->conditions,
            'bookableOnline' => $this->is_bookable_online,
            'order' => $this->sort_order,
            'staffIds' => $this->whenLoaded('professionals', fn () => $this->professionals->pluck('id')->values()->all()),
            'createdAt' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
