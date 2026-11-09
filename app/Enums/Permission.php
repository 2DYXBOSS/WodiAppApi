<?php

namespace App\Enums;

final class Permission
{
    /** Permissions du back-office (contrat ADMIN-API.md). */
    public const ADMIN = [
        'dashboard.view', 'reservations.manage', 'clients.manage', 'services.manage', 'team.manage',
        'schedules.manage', 'payments.view', 'payments.manage', 'promotions.manage', 'loyalty.manage',
        'gallery.manage', 'content.manage', 'notifications.view', 'users.manage', 'settings.manage',
    ];

    /** Permissions d'un professionnel (contrat API-MOBILE.md). */
    public const BARBER = [
        'bookings.respond', 'bookings.update_status', 'services.manage_own', 'activity.revenue.view', 'profile.edit',
    ];

    /** Matrice par défaut (à valider par Maison WODI). */
    public const MATRIX = [
        'super_admin' => self::ADMIN,
        'admin' => [
            'dashboard.view', 'reservations.manage', 'clients.manage', 'services.manage', 'team.manage',
            'schedules.manage', 'payments.view', 'payments.manage', 'promotions.manage', 'loyalty.manage',
            'gallery.manage', 'content.manage', 'notifications.view',
        ],
        'manager' => [
            'dashboard.view', 'reservations.manage', 'clients.manage', 'schedules.manage', 'payments.view',
            'notifications.view',
        ],
        // Le chiffre d'activité n'est pas visible par défaut : décision du salon.
        'barber' => ['bookings.respond', 'bookings.update_status', 'services.manage_own', 'profile.edit'],
        'client' => [],
    ];
}
