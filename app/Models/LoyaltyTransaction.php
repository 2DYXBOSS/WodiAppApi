<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyTransaction extends Model
{
    use HasUuids;

    protected $fillable = ['customer_id', 'booking_id', 'type', 'points', 'label', 'occurred_on', 'created_by'];

    protected function casts(): array
    {
        return ['occurred_on' => 'date:Y-m-d', 'points' => 'integer'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }
}
