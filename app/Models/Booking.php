<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'reference', 'customer_id', 'professional_id', 'service_id', 'starts_at', 'ends_at', 'duration_minutes',
        'location', 'service_name_snapshot', 'price_snapshot', 'amount_paid', 'status', 'payment_status',
        'customer_note', 'internal_note', 'cancellation_reason', 'cancelled_at', 'reminder_sent_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'cancelled_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'duration_minutes' => 'integer',
            'price_snapshot' => 'integer',
            'amount_paid' => 'integer',
            'status' => BookingStatus::class,
            'payment_status' => PaymentStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Booking $booking) {
            $booking->reference ??= Reference::next('WD');
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class)->withTrashed();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    public function histories(): HasMany
    {
        return $this->hasMany(BookingStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function remainingAmount(): ?int
    {
        return $this->price_snapshot === null ? null : max(0, $this->price_snapshot - $this->amount_paid);
    }
}
