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

class Professional extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id', 'reference', 'first_name', 'last_name', 'phone', 'email', 'job_title', 'bio', 'photo',
        'photo_alt', 'specialties', 'is_active', 'is_bookable', 'is_public', 'is_placeholder', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'specialties' => 'array',
            'is_active' => 'boolean',
            'is_bookable' => 'boolean',
            'is_public' => 'boolean',
            'is_placeholder' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Professional $professional) {
            $professional->reference ??= Reference::next('PRO');
            $professional->specialties ??= [];
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class)->withPivot('is_active')->withTimestamps();
    }

    public function workingHours(): HasMany
    {
        return $this->hasMany(WorkingHour::class)->orderBy('iso_day');
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(ScheduleBreak::class)->orderBy('start_time');
    }

    public function unavailabilities(): HasMany
    {
        return $this->hasMany(Unavailability::class);
    }

    public function absences(): HasMany
    {
        return $this->hasMany(Absence::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
