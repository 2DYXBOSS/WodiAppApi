<?php

namespace App\Enums;

/** Rôles serveur. Attribués uniquement par le backend. */
enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Manager = 'manager';
    case Barber = 'barber';
    case Client = 'client';

    public static function adminRoles(): array
    {
        return [self::SuperAdmin->value, self::Admin->value, self::Manager->value];
    }

    public function rank(): int
    {
        return match ($this) {
            self::SuperAdmin => 3,
            self::Admin => 2,
            self::Manager => 1,
            default => 0,
        };
    }
}
