<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'category_id', 'name', 'slug', 'description', 'duration_minutes', 'price', 'price_from', 'image',
        'available_in_salon', 'available_at_home', 'conditions', 'is_bookable_online', 'is_active',
        'is_placeholder', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'price' => 'integer',
            'price_from' => 'boolean',
            'available_in_salon' => 'boolean',
            'available_at_home' => 'boolean',
            'is_bookable_online' => 'boolean',
            'is_active' => 'boolean',
            'is_placeholder' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    public function professionals(): BelongsToMany
    {
        return $this->belongsToMany(Professional::class)->withPivot('is_active')->withTimestamps();
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** @return list<string> */
    public function locations(): array
    {
        return array_values(array_filter([
            $this->available_in_salon ? 'salon' : null,
            $this->available_at_home ? 'domicile' : null,
        ]));
    }

    /** Réservable en ligne : active, durée connue et ouverte à la réservation. */
    public function isBookable(): bool
    {
        return $this->is_active && $this->is_bookable_online && $this->duration_minutes !== null && $this->duration_minutes > 0;
    }
}
