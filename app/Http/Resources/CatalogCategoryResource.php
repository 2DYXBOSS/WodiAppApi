<?php

namespace App\Http\Resources;

use App\Models\ServiceCategory;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `GET /catalog/categories` — union `ServiceCategory` vitrine (tagline, icon, image…) et mobile (order).
 *
 * @mixin ServiceCategory
 */
class CatalogCategoryResource extends JsonResource
{
    /**
     * La vitrine exige une image (`NgOptimizedImage`) : à défaut, illustration locale du site
     * (chemin sans extension = image de `public/images/…-800|1600.webp`).
     */
    public const FALLBACK_IMAGE = 'images/demo/tools-flatlay';

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'tagline' => $this->tagline ?? '',
            'description' => $this->description ?? '',
            'icon' => $this->icon ?? 'scissors',
            'image' => Media::url($this->image) ?? self::FALLBACK_IMAGE,
            'imageAlt' => $this->image_alt ?? ($this->image ? $this->name : 'Photo d’illustration : outils de barbier'),
            'order' => $this->sort_order,
        ];
    }
}
