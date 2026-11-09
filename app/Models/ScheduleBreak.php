<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pause hebdomadaire récurrente d'un professionnel. */
class ScheduleBreak extends Model
{
    use HasUuids;

    protected $fillable = ['professional_id', 'iso_day', 'start_time', 'end_time', 'reason'];

    protected function casts(): array
    {
        return ['iso_day' => 'integer'];
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }
}
