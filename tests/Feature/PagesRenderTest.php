<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\User;
use App\Models\Verification;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Every screen added for marketplace onboarding and billing renders its
 * page component (the component file must exist) for a user allowed to see it.
 */
it('renders the seller screens', function (string $routeName, string $component) {
    $owner = User::factory()->create();
    Company::factory()->for($owner, 'owner')->pendingVerification()->create();

    $this->actingAs($owner)
        ->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    'dashboard' => ['dashboard', 'dashboard'],
    'company profile' => ['company.edit', 'company/edit'],
    'company billing' => ['company.billing.show', 'company/billing'],
    'notifications' => ['notifications.index', 'notifications/index'],
]);

it('sends users without a company to onboarding', function () {
    $buyer = userWithRole(Role::BUYER);

    $this->actingAs($buyer)->get(route('company.edit'))->assertRedirect(route('company.create'));
    $this->actingAs($buyer)->get(route('company.create'))->assertInertia(fn (Assert $page) => $page->component('company/create'));
});

it('renders the marketplace admin screens', function (string $routeName, string $component) {
    Company::factory()->verified()->create();

    $this->actingAs(userWithRole(Role::ADMIN))
        ->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    'overview' => ['admin.dashboard', 'Admin/Dashboard'],
    'verifications' => ['admin.verifications.index', 'Admin/Verifications/Index'],
    'companies' => ['admin.companies.index', 'Admin/Companies/Index'],
    'categories' => ['admin.categories.index', 'Admin/Categories/Index'],
    'seller plans' => ['admin.plans.index', 'Admin/Plans/Index'],
    'platform plan' => ['admin.billing.show', 'Admin/Billing'],
    'settings' => ['admin.settings.edit', 'Admin/Settings/Index'],
    'notifications' => ['admin.notifications.index', 'Admin/Notifications/Index'],
    'audit log' => ['admin.audit-logs.index', 'Admin/AuditLogs/Index'],
    'activity log' => ['admin.activity.index', 'Admin/Activity/Index'],
    'disputes' => ['admin.disputes.index', 'Admin/Disputes/Index'],
    'payouts' => ['admin.payouts.index', 'Admin/Payouts/Index'],
]);

it('renders a verification for review', function () {
    $verification = Verification::factory()->create();

    $this->actingAs(userWithRole(Role::VERIFIER))
        ->get(route('admin.verifications.show', $verification))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Verifications/Show')
            ->where('verification.id', $verification->id));
});

it('renders the platform screens', function (string $routeName, string $component) {
    $platformAdmin = User::factory()->create();
    $platformAdmin->forceFill(['is_platform_admin' => true])->save();

    $this->actingAs($platformAdmin)
        ->get(route($routeName, $routeName === 'platform.tenants.show' ? $this->tenant : []))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    'marketplaces' => ['platform.tenants.index', 'Platform/Tenants/Index'],
    'new marketplace' => ['platform.tenants.create', 'Platform/Tenants/Create'],
    'marketplace' => ['platform.tenants.show', 'Platform/Tenants/Show'],
    'marketplace plans' => ['platform.plans.index', 'Admin/Plans/Index'],
]);
