<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Données indispensables (production comprise) : rôles et permissions uniquement.
 * Aucun compte administrateur n'est créé ici : utiliser `php artisan wodi:create-super-admin`.
 * Données de démonstration : `php artisan db:seed --class=DemoSeeder` (refusé en production).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);
    }
}
