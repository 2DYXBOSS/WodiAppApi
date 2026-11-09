<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id', 'reference', 'first_name', 'last_name', 'phone', 'email', 'birth_date',
        'preferences', 'notes', 'loyalty_tier', 'vip_since',
    ];

    protected function casts(): array
    {
        return ['birth_date' => 'date:Y-m-d', 'vip_since' => 'date:Y-m-d'];
    }

    protected static function booted(): void
    {
        static::creating(function (Customer $customer) {
            $customer->reference ??= Reference::next('CLI');
            $customer->loyalty_tier ??= 'standard';
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function loyaltyTransactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
