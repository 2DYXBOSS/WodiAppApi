<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Indisponibilité ponctuelle (quelques heures une journée donnée). */
class Unavailability extends Model
{
    use HasUuids;

    protected $fillable = ['professional_id', 'date', 'start_time', 'end_time', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d'];
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }
}
