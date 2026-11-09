<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class GalleryImage extends Model
{
    use HasUuids;

    protected $fillable = [
        'title', 'description', 'image_path', 'alt', 'category', 'orientation', 'sort_order', 'is_published', 'is_illustration',
    ];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'is_illustration' => 'boolean', 'sort_order' => 'integer'];
    }
}
