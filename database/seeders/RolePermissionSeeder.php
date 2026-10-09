<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionCatalog::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // The registrar caches the permission table; roles are synced by name
        // below, so the freshly created rows have to be visible first.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (UserRole::cases() as $role) {
            $model = Role::findOrCreate($role->value, 'web');

            // A role that already existed may have been edited from the Peran
            // & Izin screen, and reseeding must not undo that. The superadmin
            // is the exception: it always holds everything, including any
            // permission added since the last run.
            if ($model->wasRecentlyCreated || $role === UserRole::SuperAdmin) {
                $model->syncPermissions(PermissionCatalog::defaultsFor($role));
            }
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function permissionGroups(): array
    {
        return array_map(array_keys(...), PermissionCatalog::groups());
    }
}
