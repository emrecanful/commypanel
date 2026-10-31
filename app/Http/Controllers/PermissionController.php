<?php

namespace App\Http\Controllers;

use App\Auth\Permissions;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{
    public function index(): Response
    {
        $roles = Role::query()->where('guard_name', 'web')->with('permissions:id,name')->orderBy('name')->get();
        $groups = [];

        foreach (Permissions::definitions() as $name => $definition) {
            $groups[$definition['group']][] = [
                'name' => $name,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'roles' => $roles->filter(fn (Role $role) => $role->permissions->contains('name', $name))->pluck('name')->values(),
            ];
        }

        return Inertia::render('permissions/index', [
            'groups' => array_map(fn (string $group, array $permissions) => ['group' => $group, 'permissions' => $permissions], array_keys($groups), $groups),
        ]);
    }
}
