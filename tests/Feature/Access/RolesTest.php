<?php

use App\Auth\Permissions;
use App\Auth\SyncRolesAndPermissions;
use App\Models\AuditLog;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

function roleManager(array $extra = []): User
{
    $user = User::factory()->regular()->create();
    $role = Role::findOrCreate('role-manager', 'web');
    $role->syncPermissions([Permissions::ROLES_VIEW, Permissions::ROLES_MANAGE, ...$extra]);
    $user->assignRole($role);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

beforeEach(function () {
    app(SyncRolesAndPermissions::class)();
    $this->actingAs(User::factory()->create());
});

describe('listing', function () {
    it('shows roles with permission and user counts, and locks admin', function () {
        $role = Role::findOrCreate('viewer', 'web');
        $role->syncPermissions([Permissions::APPS_VIEW]);
        User::factory()->regular()->create()->assignRole($role);

        $this->get('/roles')->assertInertia(fn (Assert $page) => $page
            ->component('roles/index')
            ->where('total_permissions', count(Permissions::names()))
            ->where('roles.0.name', 'admin')
            ->where('roles.0.locked', true)
            ->where('roles.0.can_edit', false)
            ->where('roles.0.users', 0)
            ->where('roles.1.name', 'viewer')
            ->where('roles.1.permissions', 1)
            ->where('roles.1.users', 1)
            ->where('roles.1.can_edit', true));
    });
});

describe('creating', function () {
    it('creates a role with the chosen permissions', function () {
        $this->post('/roles', ['name' => 'Support team', 'permissions' => [Permissions::APPS_VIEW, Permissions::SERVER_VIEW]])
            ->assertRedirect('/roles');

        $role = Role::findByName('Support team', 'web');
        expect($role->permissions->pluck('name')->sort()->values()->all())->toBe([Permissions::APPS_VIEW, Permissions::SERVER_VIEW])
            ->and(AuditLog::query()->where('action', 'role.created')->exists())->toBeTrue();
    });

    it('can create a role with no permissions', function () {
        $this->post('/roles', ['name' => 'Nothing', 'permissions' => []])->assertRedirect('/roles');

        expect(Role::findByName('Nothing', 'web')->permissions)->toHaveCount(0);
    });

    it('validates the name and the permissions', function (array $input, string $field) {
        Role::findOrCreate('taken', 'web');

        $this->post('/roles', $input)->assertSessionHasErrors($field);
    })->with([
        'no name' => [['name' => '', 'permissions' => []], 'name'],
        'odd characters' => [['name' => '<b>x</b>', 'permissions' => []], 'name'],
        'taken name' => [['name' => 'taken', 'permissions' => []], 'name'],
        'admin is taken' => [['name' => 'admin', 'permissions' => []], 'name'],
        'unknown permission' => [['name' => 'ok', 'permissions' => ['apps.fly']], 'permissions.0'],
        'permissions missing' => [['name' => 'ok'], 'permissions'],
    ]);

    it('cannot grant a permission the creator does not hold', function () {
        $this->actingAs(roleManager([Permissions::APPS_VIEW]));

        $this->post('/roles', ['name' => 'Sneaky', 'permissions' => [Permissions::APPS_VIEW, Permissions::SERVER_MANAGE]])->assertForbidden();
        $this->post('/roles', ['name' => 'Fine', 'permissions' => [Permissions::APPS_VIEW]])->assertRedirect('/roles');

        expect(Role::query()->where('name', 'Sneaky')->exists())->toBeFalse();
    });
});

describe('editing', function () {
    it('renames a role and changes its permissions', function () {
        $role = Role::findOrCreate('viewer', 'web');
        $role->syncPermissions([Permissions::APPS_VIEW, Permissions::SERVER_VIEW]);

        $this->put("/roles/{$role->id}", ['name' => 'Observer', 'permissions' => [Permissions::APPS_VIEW, Permissions::DOMAINS_VIEW]])
            ->assertRedirect('/roles');

        $role->refresh();
        expect($role->name)->toBe('Observer')
            ->and($role->permissions->pluck('name')->sort()->values()->all())->toBe([Permissions::APPS_VIEW, Permissions::DOMAINS_VIEW]);

        $log = AuditLog::query()->where('action', 'role.updated')->firstOrFail();
        expect($log->metadata['added'])->toBe([Permissions::DOMAINS_VIEW])->and($log->metadata['removed'])->toBe([Permissions::SERVER_VIEW]);
    });

    it('keeps the permissions of users working after a role changes', function () {
        $role = Role::findOrCreate('viewer', 'web');
        $role->syncPermissions([Permissions::APPS_VIEW]);
        $user = User::factory()->regular()->create();
        $user->assignRole($role);

        $this->put("/roles/{$role->id}", ['name' => 'viewer', 'permissions' => [Permissions::SERVER_VIEW]]);

        $this->actingAs($user->fresh());
        $this->get('/apps')->assertForbidden();
        $this->get('/server')->assertOk();
    });

    it('never edits or deletes the built-in admin role', function () {
        $admin = Role::findByName('admin', 'web');

        $this->get("/roles/{$admin->id}/edit")->assertForbidden();
        $this->put("/roles/{$admin->id}", ['name' => 'boss', 'permissions' => []])->assertForbidden();
        $this->delete("/roles/{$admin->id}")->assertForbidden();

        expect(Role::findByName('admin', 'web')->permissions->count())->toBe(count(Permissions::names()));
    });

    it('does not let someone edit a role that grants more than they hold', function () {
        $role = Role::findOrCreate('powerful', 'web');
        $role->syncPermissions([Permissions::SERVER_MANAGE]);
        $this->actingAs(roleManager());

        $this->get("/roles/{$role->id}/edit")->assertForbidden();
        $this->put("/roles/{$role->id}", ['name' => 'powerful', 'permissions' => []])->assertForbidden();
        $this->delete("/roles/{$role->id}")->assertForbidden();
    });

    it('does not let someone add a permission they lack to a role they can edit', function () {
        $role = Role::findOrCreate('small', 'web');
        $role->syncPermissions([Permissions::ROLES_VIEW]);
        $this->actingAs(roleManager());

        $this->put("/roles/{$role->id}", ['name' => 'small', 'permissions' => [Permissions::ROLES_VIEW, Permissions::USERS_DELETE]])->assertForbidden();

        expect($role->fresh()->permissions->pluck('name')->all())->toBe([Permissions::ROLES_VIEW]);
    });
});

describe('deleting', function () {
    it('deletes an unused role', function () {
        $role = Role::findOrCreate('temp', 'web');

        $this->delete("/roles/{$role->id}")->assertRedirect('/roles');

        expect(Role::query()->where('name', 'temp')->exists())->toBeFalse()
            ->and(AuditLog::query()->where('action', 'role.deleted')->exists())->toBeTrue();
    });

    it('refuses to delete a role that still has users', function () {
        $role = Role::findOrCreate('in-use', 'web');
        User::factory()->regular()->create()->assignRole($role);

        $this->delete("/roles/{$role->id}")->assertSessionHasErrors(['role' => 'in-use is assigned to 1 user. Give them another role first.']);

        expect(Role::query()->where('name', 'in-use')->exists())->toBeTrue();
    });

    it('can delete the role once its last user has another one', function () {
        $role = Role::findOrCreate('in-use', 'web');
        $user = User::factory()->regular()->create();
        $user->assignRole($role);

        $user->syncRoles([]);

        $this->delete("/roles/{$role->id}")->assertRedirect('/roles');
    });
});

describe('permissions page', function () {
    it('lists every permission by group with its explanation and the roles that hold it', function () {
        Role::findOrCreate('viewer', 'web')->syncPermissions([Permissions::APPS_VIEW]);

        $this->get('/permissions')->assertInertia(fn (Assert $page) => $page
            ->component('permissions/index')
            ->where('groups.0.group', 'Apps')
            ->where('groups.0.permissions.0.name', Permissions::APPS_VIEW)
            ->where('groups.0.permissions.0.roles', ['admin', 'viewer'])
            ->where('groups', fn ($groups) => collect($groups)->sum(fn ($g) => count($g['permissions'])) === count(Permissions::names())));
    });
});
