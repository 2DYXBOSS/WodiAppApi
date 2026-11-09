<?php

namespace App\Http\Resources;

use App\Models\GalleryImage;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Galerie — union `GalleryImage` (vitrine), `GalleryItem` (mobile) et `GalleryItem` (back-office).
 *
 * @mixin GalleryImage
 */
class GalleryImageResource extends JsonResource
{
    public bool $admin = false;

    public function toArray(Request $request): array
    {
        $url = Media::url($this->image_path);
        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'src' => $url,
            'url' => $url,
            'alt' => $this->alt ?? $this->title,
            'caption' => $this->title,
            'category' => $this->category,
            'orientation' => $this->orientation,
            'isIllustration' => $this->is_illustration,
        ];
        if ($this->admin) {
            $data += ['published' => $this->is_published, 'order' => $this->sort_order, 'createdAt' => $this->created_at?->toIso8601ZuluString()];
        }

        return $data;
    }

    public static function admin($resource): self
    {
        $r = new self($resource);
        $r->admin = true;

        return $r;
    }
}
