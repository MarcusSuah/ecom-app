<?php

use App\Enums\Role as RoleEnum;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PlatformSeeder;
use Inertia\Testing\AssertableInertia as Assert;

it('creates one default superadmin holding every permission', function () {
    config(['marketplace.superadmin.email' => 'owner@platform.test', 'marketplace.superadmin.password' => 'a-long-secret-password']);

    $this->seed(PlatformSeeder::class);
    $this->seed(PlatformSeeder::class);

    $superadmin = User::query()->where('email', 'owner@platform.test')->sole();
    $role = Role::query()->withoutGlobalScopes()->where('name', RoleEnum::SUPER_ADMIN->value)->sole();

    expect($superadmin->isPlatformAdmin())->toBeTrue()
        ->and($superadmin->email_verified_at)->not->toBeNull()
        ->and($superadmin->hasRole(RoleEnum::SUPER_ADMIN->value))->toBeTrue()
        ->and($role->permissions()->count())->toBe(Permission::query()->count());
});

it('renders the roles and permissions screens', function () {
    $admin = userWithRole(RoleEnum::ADMIN);

    $this->actingAs($admin)->get(route('admin.roles.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Roles/Index')
        ->has('permissions')
        ->where('roles.data.0.can_edit', false));

    $this->actingAs($admin)->get(route('admin.permissions.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Permissions/Index')
        ->where('permissions.data.0.is_system', true));
});

it('lets an admin create, edit and delete a role on their marketplace', function () {
    $admin = userWithRole(RoleEnum::ADMIN);
    $view = Permission::query()->where('name', 'users.view')->value('id');
    $roles = Permission::query()->where('name', 'roles.view')->value('id');

    $this->actingAs($admin)->post(route('admin.roles.store'), ['name' => 'support_agent', 'permissions' => [$view]])->assertSessionHasNoErrors();
    $role = Role::query()->where('name', 'support_agent')->sole();
    expect($role->tenant_id)->toBe($this->tenant->id)->and($role->permissions->pluck('name')->all())->toBe(['users.view']);

    $this->actingAs($admin)->put(route('admin.roles.update', $role), ['name' => 'support_agent', 'permissions' => [$view, $roles]])->assertSessionHasNoErrors();
    expect($role->fresh()->permissions)->toHaveCount(2);

    $this->actingAs($admin)->delete(route('admin.roles.destroy', $role))->assertSessionHasNoErrors();
    expect(Role::query()->where('name', 'support_agent')->exists())->toBeFalse();
});

it('keeps admins from granting permissions they do not have', function () {
    $admin = userWithRole(RoleEnum::ADMIN);
    $notTheirs = Permission::query()->where('name', 'users.delete')->value('id');

    $this->actingAs($admin)->post(route('admin.roles.store'), ['name' => 'deleter', 'permissions' => [$notTheirs]])
        ->assertSessionHasErrors('permissions');
});

it('gives a new permission to the super admin and protects system ones', function () {
    $this->seed(PlatformSeeder::class);
    $superadmin = User::query()->where('email', config('marketplace.superadmin.email'))->sole();

    $this->actingAs($superadmin)->post(route('admin.permissions.store'), ['name' => 'exports.schedule', 'module' => 'Reports'])->assertSessionHasNoErrors();
    expect(Role::query()->withoutGlobalScopes()->where('name', RoleEnum::SUPER_ADMIN->value)->sole()->hasPermissionTo('exports.schedule'))->toBeTrue();

    $system = Permission::query()->where('name', 'users.view')->sole();
    $this->actingAs($superadmin)->delete(route('admin.permissions.destroy', $system))->assertSessionHasErrors('permission');
});
