<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['booking_id', 'old_status', 'new_status', 'action', 'detail', 'changed_by', 'changed_by_name'];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
