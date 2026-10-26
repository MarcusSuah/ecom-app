<?php

use App\Enums\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('lets marketplace admins change a member\'s roles on their marketplace', function () {
    $member = userWithRole(Role::BUYER);

    $this->actingAs(userWithRole(Role::ADMIN))
        ->put(route('admin.users.roles.update', $member), ['roles' => [Role::VERIFIER->value]])
        ->assertRedirect();

    expect($member->fresh()->getRoleNames()->all())->toBe([Role::VERIFIER->value]);
});

it('does not let marketplace admins edit or block the shared account', function () {
    $member = userWithRole(Role::SUPPLIER);
    $admin = userWithRole(Role::ADMIN);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $member), ['name' => $member->name, 'email' => 'attacker@example.com'])
        ->assertForbidden();
    $this->actingAs($admin)->post(route('admin.users.deactivate', $member))->assertForbidden();

    expect($member->fresh()->email)->not->toBe('attacker@example.com');
});

it('lists only people with a role on this marketplace', function () {
    $member = userWithRole(Role::BUYER);
    User::factory()->create();

    $this->actingAs(userWithRole(Role::ADMIN))
        ->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 2)
            ->where('users.data', fn ($users) => collect($users)->pluck('id')->contains($member->id)));
});
