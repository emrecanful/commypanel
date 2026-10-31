<?php

namespace App\Auth;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Makes the database match the permission list in code, and keeps the
 * "admin" role holding every permission. Safe to run any number of times:
 * on first setup, and after every upgrade (new releases add permissions).
 */
class SyncRolesAndPermissions
{
    public const ADMIN_ROLE = 'admin';

    public function __invoke(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        foreach (Permissions::names() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Permissions that no longer exist in code must not linger on roles.
        Permission::query()->whereNotIn('name', Permissions::names())->get()->each->delete();

        Role::findOrCreate(self::ADMIN_ROLE, 'web')->syncPermissions(Permission::query()->where('guard_name', 'web')->get());

        $registrar->forgetCachedPermissions();
    }
}
