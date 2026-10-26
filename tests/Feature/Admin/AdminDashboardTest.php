<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Inertia\Testing\AssertableInertia as Assert;

it('shows counts computed from this marketplace\'s records', function () {
    $admin = userWithRole(Role::ADMIN);
    Company::factory()->supplier()->verified()->create();
    Company::factory()->supplier()->pendingVerification()->create();
    Company::factory()->supplier()->rejectedVerification()->create();
    Company::factory()->shop()->verified()->create();
    app(CurrentTenant::class)->runAs(Tenant::factory()->create(), fn () => Company::factory()->supplier()->verified()->create());

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard')
            ->where('counts.suppliers', 3)
            ->where('counts.shops', 1)
            ->where('counts.verified', 1)
            ->where('counts.pending_verifications', 1)
            // The admin and the four company owners.
            ->where('counts.members', 5));
});

it('shows the table behind the card that was chosen', function () {
    $admin = userWithRole(Role::ADMIN);
    $pending = Company::factory()->supplier()->pendingVerification()->create(['name' => 'Waiting Ltd']);
    Company::factory()->supplier()->verified()->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard', ['tab' => 'pending_verifications']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'pending_verifications')
            ->has('table.rows', 1)
            ->where('table.rows.0.cells.0', $pending->name));

    $this->actingAs($admin)
        ->get(route('admin.dashboard', ['tab' => 'nonsense']))
        ->assertInertia(fn (Assert $page) => $page->where('tab', 'orders'));
});

it('is only for marketplace admins', function () {
    $this->actingAs(userWithRole(Role::VERIFIER))
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});
