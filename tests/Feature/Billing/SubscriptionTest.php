<?php

use App\Enums\InvoiceStatus;
use App\Enums\PlanLimit;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\User;

function sellerWithCompany(): array
{
    $owner = User::factory()->create();
    $company = Company::factory()->for($owner, 'owner')->create();

    return [$owner, $company];
}

it('falls back to the marketplace default plan when a company has no subscription', function () {
    $default = Plan::factory()->sellerOf($this->tenant)->default()->limit(PlanLimit::MAX_PRODUCTS, 10)->create();
    [, $company] = sellerWithCompany();

    expect($company->effectivePlan()->is($default))->toBeTrue()
        ->and($company->planLimit(PlanLimit::MAX_PRODUCTS))->toBe(10)
        ->and($company->withinPlanLimit(PlanLimit::MAX_PRODUCTS, 9))->toBeTrue()
        ->and($company->withinPlanLimit(PlanLimit::MAX_PRODUCTS, 10))->toBeFalse();
});

it('starts a free plan at once', function () {
    $free = Plan::factory()->sellerOf($this->tenant)->create();
    [$owner, $company] = sellerWithCompany();

    $this->actingAs($owner)
        ->post(route('company.billing.subscribe'), ['plan_id' => $free->id])
        ->assertRedirect(route('company.billing.show'));

    $subscription = $company->currentSubscription();
    expect($subscription->plan_id)->toBe($free->id)
        ->and($subscription->status)->toBe(SubscriptionStatus::ACTIVE)
        ->and(SubscriptionInvoice::count())->toBe(0);
});

it('gives a trial on paid plans only once per company', function () {
    $growth = Plan::factory()->sellerOf($this->tenant)->paid()->withTrial(14)->create();
    $pro = Plan::factory()->sellerOf($this->tenant)->paid(75000)->withTrial(14)->create();
    [$owner, $company] = sellerWithCompany();

    $this->actingAs($owner)->post(route('company.billing.subscribe'), ['plan_id' => $growth->id]);

    $trial = $company->currentSubscription();
    expect($trial->status)->toBe(SubscriptionStatus::TRIALING)
        ->and($trial->trial_ends_at->toDateString())->toBe(now()->addDays(14)->toDateString());

    $this->actingAs($owner)->post(route('company.billing.subscribe'), ['plan_id' => $pro->id]);

    $paid = $company->currentSubscription();
    expect($paid->plan_id)->toBe($pro->id)
        ->and($paid->status)->toBe(SubscriptionStatus::ACTIVE)
        ->and($paid->trial_ends_at)->toBeNull()
        ->and($trial->fresh()->status)->toBe(SubscriptionStatus::EXPIRED);
});

it('charges a paid plan through the gateway and replaces the previous plan', function () {
    $starter = Plan::factory()->sellerOf($this->tenant)->create();
    $pro = Plan::factory()->sellerOf($this->tenant)->paid(75000)->create();
    [$owner, $company] = sellerWithCompany();
    $old = Subscription::factory()->forSubscriber($company, $starter)->create();

    $this->actingAs($owner)->post(route('company.billing.subscribe'), ['plan_id' => $pro->id]);

    $invoice = SubscriptionInvoice::sole();
    expect($invoice->status)->toBe(InvoiceStatus::PAID)
        ->and($invoice->provider)->toBe('fake')
        ->and($invoice->amount)->toBe(75000)
        ->and($company->currentSubscription()->plan_id)->toBe($pro->id)
        ->and($old->fresh()->status)->toBe(SubscriptionStatus::EXPIRED);
});

it('keeps the price a company subscribed at when the plan price changes', function () {
    $pro = Plan::factory()->sellerOf($this->tenant)->paid(75000)->create();
    [$owner, $company] = sellerWithCompany();
    $this->actingAs($owner)->post(route('company.billing.subscribe'), ['plan_id' => $pro->id]);

    $pro->update(['price' => 99000]);

    expect($company->currentSubscription()->price)->toBe(75000);
});

it('does not let a company subscribe to another marketplace\'s plan', function (Plan $plan) {
    [$owner, $company] = sellerWithCompany();

    $this->actingAs($owner)
        ->post(route('company.billing.subscribe'), ['plan_id' => $plan->id])
        ->assertNotFound();

    expect($company->subscriptions()->count())->toBe(0);
})->with([
    'another marketplace\'s seller plan' => [fn () => Plan::factory()->sellerOf(Tenant::factory()->create())->create()],
    'a marketplace plan' => [fn () => Plan::factory()->create()],
]);

it('keeps a cancelled plan until the end of the paid period', function () {
    $pro = Plan::factory()->sellerOf($this->tenant)->paid()->create();
    [$owner, $company] = sellerWithCompany();
    $subscription = Subscription::factory()->forSubscriber($company, $pro)->create(['current_period_ends_at' => now()->addDays(10)]);

    $this->actingAs($owner)->post(route('company.billing.cancel'))->assertRedirect();

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::CANCELLED)
        ->and($subscription->ends_at->toDateString())->toBe(now()->addDays(10)->toDateString())
        ->and($company->currentSubscription()?->is($subscription))->toBeTrue();

    $this->travel(11)->days();
    expect($company->currentSubscription())->toBeNull();
});

it('only lets the owner manage the company plan', function () {
    $plan = Plan::factory()->sellerOf($this->tenant)->create();
    Company::factory()->create();

    $this->actingAs(userWithRole(Role::BUYER))
        ->post(route('company.billing.subscribe'), ['plan_id' => $plan->id])
        ->assertNotFound();
});

it('lets admins record an offline payment for a company', function () {
    $pro = Plan::factory()->sellerOf($this->tenant)->paid(75000)->create();
    [, $company] = sellerWithCompany();

    $this->actingAs(userWithRole(Role::ADMIN))
        ->post(route('admin.companies.plan', $company), ['plan_id' => $pro->id, 'periods' => 3, 'reference' => 'BK-001'])
        ->assertRedirect();

    $subscription = $company->currentSubscription();
    $invoice = $subscription->invoices()->sole();
    expect($subscription->plan_id)->toBe($pro->id)
        ->and($subscription->current_period_ends_at->toDateString())->toBe(now()->addMonthsNoOverflow(3)->toDateString())
        ->and($invoice->amount)->toBe(225000)
        ->and($invoice->provider)->toBe('manual')
        ->and($invoice->provider_reference)->toBe('BK-001');
});

it('only features companies whose plan includes featured listings', function (bool $planIncludesFeature) {
    Plan::factory()->sellerOf($this->tenant)->default()->limit(PlanLimit::FEATURED_LISTING, $planIncludesFeature)->create();
    $company = Company::factory()->create();

    $response = $this->actingAs(userWithRole(Role::ADMIN))
        ->put(route('admin.companies.feature', $company), ['is_featured' => true]);

    $planIncludesFeature
        ? $response->assertSessionHasNoErrors()
        : $response->assertSessionHasErrors(['is_featured' => "{$company->name}'s plan does not include featured listings."]);
    expect($company->fresh()->is_featured)->toBe($planIncludesFeature);
})->with([
    'plan includes featured listing' => [true],
    'plan without featured listing' => [false],
]);

it('lets the marketplace admin manage seller plans but not another marketplace\'s', function () {
    $admin = userWithRole(Role::ADMIN);
    $foreignPlan = Plan::factory()->sellerOf(Tenant::factory()->create())->create();

    $this->actingAs($admin)
        ->post(route('admin.plans.store'), [
            'name' => 'Growth', 'price' => '25000', 'currency' => 'RWF', 'interval' => 'month',
            'trial_days' => 14, 'sort_order' => 10, 'is_default' => true, 'is_active' => true,
            'limits' => ['max_products' => '100', 'monthly_quotes' => '', 'commission_percent' => '5', 'featured_listing' => '1'],
        ])
        ->assertSessionHasNoErrors();

    $plan = Plan::query()->forSellersOf($this->tenant)->sole();
    expect($plan->price)->toBe(25000)
        ->and($plan->limit(PlanLimit::MAX_PRODUCTS))->toBe(100)
        ->and($plan->limit(PlanLimit::MONTHLY_QUOTES))->toBeNull()
        ->and($plan->limit(PlanLimit::FEATURED_LISTING))->toBeTrue();

    $this->actingAs($admin)
        ->put(route('admin.plans.update', $foreignPlan), ['name' => 'Hijacked'])
        ->assertForbidden();
});

it('converts typed prices to minor units for USD plans', function () {
    $platformAdmin = User::factory()->create();
    $platformAdmin->forceFill(['is_platform_admin' => true])->save();

    $this->actingAs($platformAdmin)
        ->post(route('platform.plans.store'), [
            'name' => 'Scale', 'price' => '199.99', 'currency' => 'USD', 'interval' => 'month',
            'trial_days' => 0, 'sort_order' => 0, 'limits' => ['max_companies' => '500', 'custom_domain' => '1'],
        ])
        ->assertSessionHasNoErrors();

    $plan = Plan::query()->forMarketplaces()->where('name', 'Scale')->sole();
    expect($plan->price)->toBe(19999)
        ->and($plan->formattedPrice())->toBe('USD 199.99 / month');
});
