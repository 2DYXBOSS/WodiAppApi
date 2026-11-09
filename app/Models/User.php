<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, HasUuids, Notifiable, SoftDeletes;

    protected string $guard_name = 'web';

    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone', 'birth_date', 'avatar_path', 'password',
        'is_active', 'last_login_at', 'notify_booking_updates', 'notify_reminders', 'notify_promotions',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'birth_date' => 'date:Y-m-d',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'notify_booking_updates' => 'boolean',
            'notify_reminders' => 'boolean',
            'notify_promotions' => 'boolean',
        ];
    }

    protected function email(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : mb_strtolower(trim($value)));
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    public function professional(): HasOne
    {
        return $this->hasOne(Professional::class);
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /** Rôle principal du compte (un compte = un rôle). */
    public function primaryRole(): ?string
    {
        return $this->getRoleNames()->first();
    }

    /** Rôle exposé à l'application mobile : client | barber | admin. */
    public function mobileRole(): string
    {
        return match ($this->primaryRole()) {
            Role::Client->value => 'client',
            Role::Barber->value => 'barber',
            default => 'admin',
        };
    }

    public function isAdmin(): bool
    {
        return $this->hasAnyRole(Role::adminRoles());
    }

    public function isClient(): bool
    {
        return $this->hasRole(Role::Client->value);
    }

    public function isBarber(): bool
    {
        return $this->hasRole(Role::Barber->value);
    }

    public function roleRank(): int
    {
        $role = Role::tryFrom((string) $this->primaryRole());

        return $role?->rank() ?? 0;
    }
}
