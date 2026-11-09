<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCategory extends Model
{
    use HasFactory, HasUuids;

    /** Icônes connues du site vitrine (src/app/shared/components/icon/icons.ts) : liste fermée. */
    public const ICONS = [
        'scissors', 'razor', 'crown', 'sparkles', 'droplet', 'home', 'star', 'heart', 'user', 'users',
        'shield-check', 'calendar', 'clock', 'image', 'tag', 'layers', 'bell', 'smartphone', 'credit-card',
    ];

    protected $fillable = ['name', 'slug', 'tagline', 'description', 'icon', 'image', 'image_alt', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'category_id');
    }
}
