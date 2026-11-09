<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    /** Statuts qui occupent un créneau (identiques à la contrainte d'exclusion PostgreSQL). */
    public static function blocking(): array
    {
        return [self::Pending, self::Confirmed, self::InProgress, self::Completed];
    }

    /** Statuts encore modifiables / annulables. */
    public static function active(): array
    {
        return [self::Pending, self::Confirmed];
    }

    public static function values(array $cases): array
    {
        return array_map(fn (self $s) => $s->value, $cases);
    }

    /** Transitions autorisées (identiques côté back-office et application mobile). */
    public function canTransitionTo(self $to): bool
    {
        return in_array($to, match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::InProgress, self::Completed, self::Cancelled, self::NoShow],
            self::InProgress => [self::Completed],
            default => [],
        }, true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Confirmed => 'Confirmée',
            self::InProgress => 'En cours',
            self::Completed => 'Terminée',
            self::Cancelled => 'Annulée',
            self::NoShow => 'Absence',
        };
    }
}
