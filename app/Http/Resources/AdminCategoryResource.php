<?php

namespace App\Http\Resources;

use App\Models\ServiceCategory;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ServiceCategory */
class AdminCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description ?? '',
            'order' => $this->sort_order,
            'tagline' => $this->tagline,
            'icon' => $this->icon,
            'image' => Media::url($this->image),
            'imageAlt' => $this->image_alt,
            'active' => $this->is_active,
            'createdAt' => $this->created_at?->toIso8601ZuluString(),
            'servicesCount' => (int) ($this->services_count ?? 0),
        ];
    }
}
