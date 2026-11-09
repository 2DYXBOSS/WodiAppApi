<?php

namespace App\Http\Resources;

use App\Models\Service;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `GET /catalog/services` — union `SalonService` vitrine et mobile.
 * Nécessite `category` et `professionals` (éligibles) préchargés.
 *
 * @mixin Service
 */
class CatalogServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $staffIds = $this->professionals->pluck('id')->values()->all();

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'categoryId' => $this->category_id,
            'categoryName' => $this->category?->name ?? '',
            'name' => $this->name,
            'description' => $this->description ?? '',
            'durationMinutes' => $this->duration_minutes,
            'price' => $this->price,
            'priceFrom' => $this->price_from,
            'image' => Media::url($this->image),
            'locations' => $this->locations(),
            'conditions' => $this->conditions,
            'bookable' => $this->isBookable() && $staffIds !== [],
            'staffIds' => $staffIds,
            'isPlaceholder' => $this->is_placeholder,
        ];
    }
}
