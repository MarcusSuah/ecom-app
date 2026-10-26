<?php

use App\Enums\CompanyType;
use App\Enums\PlanLimit;
use App\Enums\Role;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;

function companyPayload(array $overrides = []): array
{
    return [
        'type' => 'supplier',
        'name' => 'Kigali Agro Ltd',
        'description' => 'Maize and beans in bulk.',
        'country' => 'rw',
        'city' => 'Kigali',
        'phone' => '+250788000000',
        'email' => 'sales@kigaliagro.rw',
        ...$overrides,
    ];
}

it('gives the owner the role that matches the company type', function (string $type, Role $expectedRole) {
    $user = userWithRole(Role::BUYER);

    $this->actingAs($user)
        ->post(route('company.store'), companyPayload(['type' => $type]))
        ->assertRedirect(route('company.edit'));

    $company = Company::sole();
    expect($company->owner_id)->toBe($user->id)
        ->and($company->type)->toBe(CompanyType::from($type))
        ->and($company->country)->toBe('RW')
        ->and($company->tenant_id)->toBe($this->tenant->id)
        ->and($user->fresh()->hasRole($expectedRole->value))->toBeTrue();
})->with([
    'supplier' => ['supplier', Role::SUPPLIER],
    'shop' => ['shop', Role::SHOP_OWNER],
]);

it('does not let a user create a second company on the same marketplace', function () {
    $owner = User::factory()->create();
    Company::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner)
        ->post(route('company.store'), companyPayload(['name' => 'Second Company']))
        ->assertForbidden();

    expect(Company::count())->toBe(1);
});

it('validates the company profile', function () {
    $this->actingAs(userWithRole(Role::BUYER))
        ->post(route('company.store'), ['type' => 'factory'])
        ->assertSessionHasErrors(['type', 'name', 'country']);

    expect(Company::count())->toBe(0);
});

it('lets owners edit their company but not change its type', function () {
    $owner = User::factory()->create();
    $company = Company::factory()->for($owner, 'owner')->supplier()->create();

    $this->actingAs($owner)
        ->put(route('company.update'), companyPayload(['type' => 'shop', 'name' => 'Renamed Supplies']))
        ->assertRedirect(route('company.edit'));

    expect($company->fresh())
        ->name->toBe('Renamed Supplies')
        ->type->toBe(CompanyType::SUPPLIER);
});

it('stops new sellers once the marketplace reaches its plan limit', function () {
    $limitedPlan = Plan::factory()->limit(PlanLimit::MAX_COMPANIES, 1)->create();
    $this->tenant->subscriptions()->update(['plan_id' => $limitedPlan->id]);
    Company::factory()->create();

    $this->actingAs(userWithRole(Role::BUYER))
        ->post(route('company.store'), companyPayload())
        ->assertSessionHasErrors(['name' => 'This marketplace is not accepting new sellers right now. Please try again later.']);

    expect(Company::count())->toBe(1);
});

it('lets the same user own one company on each marketplace', function () {
    $owner = User::factory()->create();
    Company::factory()->for($owner, 'owner')->create();

    $otherMarketplace = Tenant::factory()->create();
    Subscription::factory()->forSubscriber($otherMarketplace, Plan::factory()->create())->create();
    $otherMarketplace->makeCurrent();

    $this->actingAs($owner)
        ->post('http://'.$otherMarketplace->slug.'.'.config('tenancy.central_domain').'/company', companyPayload(['type' => 'shop']))
        ->assertRedirect();

    expect(Company::withoutGlobalScopes()->where('owner_id', $owner->id)->count())->toBe(2)
        ->and($owner->fresh()->hasRole(Role::SHOP_OWNER->value))->toBeTrue();

    $this->tenant->makeCurrent();
    expect($owner->fresh()->hasRole(Role::SHOP_OWNER->value))->toBeFalse()
        ->and($owner->fresh()->hasRole(Role::SUPPLIER->value))->toBeTrue();
});
