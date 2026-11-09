<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkingHour extends Model
{
    use HasUuids;

    protected $fillable = ['professional_id', 'iso_day', 'is_working', 'start_time', 'end_time'];

    protected function casts(): array
    {
        return ['iso_day' => 'integer', 'is_working' => 'boolean'];
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }
}
