<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'description', 'discount_type', 'value', 'start_date', 'end_date', 'conditions', 'is_active'];

    protected function casts(): array
    {
        return ['value' => 'integer', 'is_active' => 'boolean', 'start_date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d'];
    }

    /** Promotions actives et en cours à la date du salon. */
    public function scopeCurrent(Builder $query, string $today): Builder
    {
        return $query->where('is_active', true)->where('start_date', '<=', $today)->where('end_date', '>=', $today);
    }
}
