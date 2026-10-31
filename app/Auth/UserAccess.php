<?php

namespace App\Auth;

use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Who may change whom. The rule that keeps roles from becoming a way to gain
 * more access than you were given: you can only hand out, edit or touch what
 * is covered by permissions you hold yourself. The super user is outside all
 * of it: nobody manages it, and it can do everything.
 */
class UserAccess
{
    /**
     * @return list<string>
     */
    public function permissionsOf(User $user): array
    {
        if ($user->isSuper()) {
            return Permissions::names();
        }

        /** @var list<string> $names */
        $names = $user->getAllPermissions()->pluck('name')->values()->all();

        return $names;
    }

    /**
     * @param  iterable<string>  $permissions
     */
    public function holdsAll(User $actor, iterable $permissions): bool
    {
        $held = $this->permissionsOf($actor);

        foreach ($permissions as $permission) {
            if (! in_array($permission, $held, true)) {
                return false;
            }
        }

        return true;
    }

    public function canHandOutRole(User $actor, Role $role): bool
    {
        return $this->holdsAll($actor, $role->permissions->pluck('name'));
    }

    /**
     * Whether $actor may edit or delete $target's account.
     */
    public function canManage(User $actor, User $target): bool
    {
        if ($target->isSuper()) {
            return false;
        }

        return $this->holdsAll($actor, $this->permissionsOf($target));
    }
}
