<?php

namespace App\Models;

use App\Enums\AbsenceType;
use App\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Congé / absence / formation / fermeture exceptionnelle, sur des journées entières. */
class Absence extends Model
{
    use HasUuids;

    protected $fillable = [
        'professional_id', 'type', 'start_date', 'end_date', 'reason', 'status', 'created_by', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => AbsenceType::class,
            'status' => ApprovalStatus::class,
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'reviewed_at' => 'datetime',
        ];
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }
}
