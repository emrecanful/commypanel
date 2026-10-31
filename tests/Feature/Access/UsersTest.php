<?php

use App\Auth\Permissions;
use App\Auth\SyncRolesAndPermissions;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

function userInput(array $overrides = []): array
{
    return [
        'name' => 'Sam Support', 'email' => 'sam@example.com',
        'password' => 'a-long-password-123', 'password_confirmation' => 'a-long-password-123',
        'role' => '',
        ...$overrides,
    ];
}

function roleWith(array $permissions, string $name = 'role-x'): Role
{
    $role = Role::findOrCreate($name, 'web');
    $role->syncPermissions($permissions);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $role;
}

/** A manager who may handle users and hold the given extra permissions. */
function manager(array $extra = []): User
{
    app(SyncRolesAndPermissions::class)();
    $user = User::factory()->regular()->create();
    $user->assignRole(roleWith([Permissions::USERS_VIEW, Permissions::USERS_CREATE, Permissions::USERS_UPDATE, Permissions::USERS_DELETE, ...$extra], 'manager'));

    return $user;
}

beforeEach(function () {
    app(SyncRolesAndPermissions::class)();
    $this->super = User::factory()->create(['name' => 'Root', 'email' => 'root@example.com']);
    $this->actingAs($this->super);
});

describe('listing', function () {
    it('shows users with their role and what the viewer may do', function () {
        $other = User::factory()->regular()->create(['name' => 'Ola']);
        $other->assignRole(roleWith([Permissions::APPS_VIEW], 'viewer'));

        $this->get('/users')->assertInertia(fn (Assert $page) => $page
            ->component('users/index')
            ->where('users.0.is_super', true)
            ->where('users.0.is_you', true)
            ->where('users.0.can_edit', false)
            ->where('users.0.can_delete', false)
            ->where('users.1.role', 'viewer')
            ->where('users.1.can_edit', true)
            ->where('users.1.can_delete', true));
    });
});

describe('creating', function () {
    it('creates a user with a role who can then sign in', function () {
        $role = roleWith([Permissions::APPS_VIEW], 'viewer');

        $this->post('/users', userInput(['role' => $role->id]))->assertRedirect('/users');

        $user = User::query()->where('email', 'sam@example.com')->firstOrFail();
        expect($user->isSuper())->toBeFalse()
            ->and($user->hasRole('viewer'))->toBeTrue()
            ->and(Hash::check('a-long-password-123', $user->password))->toBeTrue()
            ->and(AuditLog::query()->where('action', 'user.created')->exists())->toBeTrue();

        auth()->logout();
        $this->post('/login', ['email' => 'sam@example.com', 'password' => 'a-long-password-123'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    });

    it('creates a user without a role', function () {
        $this->post('/users', userInput())->assertRedirect('/users');

        expect(User::query()->where('email', 'sam@example.com')->first()->roles)->toHaveCount(0);
    });

    it('never makes a user a super user, whatever is submitted', function () {
        $this->post('/users', userInput(['is_super' => true]));

        expect(User::query()->where('email', 'sam@example.com')->first()->isSuper())->toBeFalse();
    });

    it('validates the input', function (array $overrides, string $field) {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post('/users', userInput($overrides))->assertSessionHasErrors($field);
    })->with([
        'no name' => [['name' => ''], 'name'],
        'bad email' => [['email' => 'nope'], 'email'],
        'taken email' => [['email' => 'taken@example.com'], 'email'],
        'mismatched password' => [['password_confirmation' => 'different'], 'password'],
        'unknown role' => [['role' => 9999], 'role'],
    ]);

    it('cannot hand out a role that grants more than the creator has', function () {
        $actor = manager();
        $big = roleWith([Permissions::USERS_VIEW, Permissions::SERVER_MANAGE], 'big');
        $this->actingAs($actor);

        $this->post('/users', userInput(['role' => $big->id]))->assertSessionHasErrors('role');

        expect(User::query()->where('email', 'sam@example.com')->exists())->toBeFalse();
    });

    it('can hand out a role within its own permissions', function () {
        $actor = manager([Permissions::APPS_VIEW]);
        $small = roleWith([Permissions::APPS_VIEW], 'small');
        $this->actingAs($actor);

        $this->post('/users', userInput(['role' => $small->id]))->assertRedirect('/users');
    });
});

describe('updating', function () {
    it('changes name, email, role and password, and signs the user out everywhere', function () {
        config(['session.driver' => 'database']);
        $user = User::factory()->regular()->create(['email' => 'old@example.com']);
        $viewer = roleWith([Permissions::APPS_VIEW], 'viewer');
        $sessions = DB::connection(config('session.connection'));
        $sessions->table('sessions')->insert(['id' => 's1', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

        $this->put("/users/{$user->id}", userInput(['name' => 'New Name', 'email' => 'new@example.com', 'role' => $viewer->id, 'password' => 'another-long-pass-9', 'password_confirmation' => 'another-long-pass-9']))
            ->assertRedirect('/users');

        $user->refresh();
        expect($user->name)->toBe('New Name')->and($user->email)->toBe('new@example.com')
            ->and($user->hasRole('viewer'))->toBeTrue()
            ->and(Hash::check('another-long-pass-9', $user->password))->toBeTrue()
            ->and($sessions->table('sessions')->where('user_id', $user->id)->count())->toBe(0)
            ->and(AuditLog::query()->where('action', 'user.updated')->exists())->toBeTrue();
    });

    it('keeps the password when none is given, and can remove the role', function () {
        $user = User::factory()->regular()->create();
        $user->assignRole(roleWith([Permissions::APPS_VIEW], 'viewer'));
        $hash = $user->password;

        $this->put("/users/{$user->id}", ['name' => $user->name, 'email' => $user->email, 'password' => '', 'password_confirmation' => '', 'role' => '']);

        $user->refresh();
        expect($user->password)->toBe($hash)->and($user->roles)->toHaveCount(0);
    });

    it('does not let anyone, even the super user, change the super user', function () {
        $this->get("/users/{$this->super->id}/edit")->assertForbidden();
        $this->put("/users/{$this->super->id}", userInput(['name' => 'Hacked', 'email' => 'root@example.com']))->assertForbidden();

        expect($this->super->fresh()->name)->toBe('Root');

        $this->actingAs(manager());
        $this->put("/users/{$this->super->id}", userInput(['name' => 'Hacked', 'email' => 'root@example.com']))->assertForbidden();
        $this->delete("/users/{$this->super->id}")->assertForbidden();
        expect(User::query()->whereKey($this->super->id)->exists())->toBeTrue();
    });

    it('does not let a manager change someone with more access', function () {
        $actor = manager();
        $admin = User::factory()->regular()->create();
        $admin->assignRole('admin');
        $this->actingAs($actor);

        $this->get("/users/{$admin->id}/edit")->assertForbidden();
        $this->put("/users/{$admin->id}", userInput(['email' => $admin->email, 'password' => 'a-long-password-123']))->assertForbidden();
        $this->delete("/users/{$admin->id}")->assertForbidden();

        expect($admin->fresh()->name)->toBe($admin->name);
    });

    it('does not let someone change their own role', function () {
        $actor = manager([Permissions::APPS_VIEW]);
        $other = roleWith([Permissions::APPS_VIEW], 'other');
        $this->actingAs($actor);

        $this->put("/users/{$actor->id}", userInput(['name' => $actor->name, 'email' => $actor->email, 'password' => '', 'password_confirmation' => '', 'role' => $other->id]))
            ->assertSessionHasErrors('role');
        $this->put("/users/{$actor->id}", userInput(['name' => $actor->name, 'email' => $actor->email, 'password' => '', 'password_confirmation' => '', 'role' => '']))
            ->assertSessionHasErrors('role');

        expect($actor->fresh()->hasRole('manager'))->toBeTrue();
    });

    it('lets someone edit their own name', function () {
        $actor = manager();
        $role = $actor->roles->first();
        $this->actingAs($actor);

        $this->put("/users/{$actor->id}", userInput(['name' => 'Renamed', 'email' => $actor->email, 'password' => '', 'password_confirmation' => '', 'role' => $role->id]))
            ->assertRedirect('/users');

        expect($actor->fresh()->name)->toBe('Renamed');
    });

    it('cannot promote someone to a role beyond the editor', function () {
        $actor = manager();
        $target = User::factory()->regular()->create();
        $this->actingAs($actor);

        $this->put("/users/{$target->id}", userInput(['name' => $target->name, 'email' => $target->email, 'password' => '', 'password_confirmation' => '', 'role' => Role::findByName('admin', 'web')->id]))
            ->assertSessionHasErrors('role');

        expect($target->fresh()->roles)->toHaveCount(0);
    });
});

describe('deleting', function () {
    it('deletes a user, signs them out, and keeps the audit trail', function () {
        $user = User::factory()->regular()->create(['email' => 'gone@example.com']);

        $this->delete("/users/{$user->id}")->assertRedirect('/users');

        expect(User::query()->whereKey($user->id)->exists())->toBeFalse()
            ->and(AuditLog::query()->where('action', 'user.deleted')->exists())->toBeTrue();
    });

    it('does not let someone delete themselves', function () {
        $actor = manager();
        $this->actingAs($actor);

        $this->delete("/users/{$actor->id}")->assertSessionHasErrors('user');

        expect(User::query()->whereKey($actor->id)->exists())->toBeTrue();
    });
});

describe('super user', function () {
    it('is the only super user, with no way to become one through the panel', function () {
        $this->post('/users', userInput());
        $this->put('/users/'.User::query()->where('email', 'sam@example.com')->value('id'), userInput(['is_super' => true]));

        expect(User::query()->where('is_super', true)->count())->toBe(1);
    });

    it('is still recoverable with admin:reset', function () {
        $this->artisan('admin:reset')->assertSuccessful();

        expect(Hash::check('password', $this->super->fresh()->password))->toBeFalse();
    });
});
