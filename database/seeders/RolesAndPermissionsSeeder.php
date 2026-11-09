<?php

namespace Database\Seeders;

use App\Enums\Permission as Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Rôles et permissions (idempotent : peut être relancé en production). */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([...Permissions::ADMIN, ...Permissions::BARBER] as $name) {
            Permission::findOrCreate($name, 'web');
        }
        foreach (Permissions::MATRIX as $role => $permissions) {
            Role::findOrCreate($role, 'web')->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
