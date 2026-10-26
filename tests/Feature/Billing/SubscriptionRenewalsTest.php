<?php

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Support\CurrentTenant;

beforeEach(function () {
    // The scheduled command runs from the console, with no marketplace active.
    app(CurrentTenant::class)->set(null);
});

function subscriptionFor(Plan $plan, SubscriptionStatus $status, array $attributes = []): Subscription
{
    $company = app(CurrentTenant::class)->runAs(test()->tenant, fn () => Company::factory()->create());

    return Subscription::factory()->forSubscriber($company, $plan)->status($status)->create($attributes);
}

it('invoices an ended paid period and marks the subscription past due', function () {
    $plan = Plan::factory()->sellerOf($this->tenant)->paid(25000)->create();
    $subscription = subscriptionFor($plan, SubscriptionStatus::ACTIVE, ['current_period_ends_at' => now()->subHour()]);

    $this->artisan('billing:process-renewals')->assertSuccessful();

    $invoice = SubscriptionInvoice::withoutGlobalScopes()->sole();
    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::PAST_DUE)
        ->and($invoice->status)->toBe(InvoiceStatus::PENDING)
        ->and($invoice->amount)->toBe(25000)
        ->and($invoice->tenant_id)->toBe($this->tenant->id)
        ->and($invoice->period_starts_at->equalTo($subscription->current_period_ends_at))->toBeTrue();
});

it('invoices the first period when a trial ends', function () {
    $plan = Plan::factory()->sellerOf($this->tenant)->paid()->withTrial()->create();
    $subscription = subscriptionFor($plan, SubscriptionStatus::TRIALING, ['trial_ends_at' => now()->subHour(), 'current_period_ends_at' => now()->subHour()]);

    $this->artisan('billing:process-renewals')->assertSuccessful();

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::PAST_DUE)
        ->and(SubscriptionInvoice::withoutGlobalScopes()->count())->toBe(1);
});

it('rolls free plans on to the next period without an invoice', function () {
    $plan = Plan::factory()->sellerOf($this->tenant)->create();
    $endedAt = now()->subHour();
    $subscription = subscriptionFor($plan, SubscriptionStatus::ACTIVE, ['current_period_ends_at' => $endedAt]);

    $this->artisan('billing:process-renewals')->assertSuccessful();

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::ACTIVE)
        ->and($subscription->current_period_ends_at->toDateString())->toBe($endedAt->addMonthNoOverflow()->toDateString())
        ->and(SubscriptionInvoice::withoutGlobalScopes()->count())->toBe(0);
});

it('expires past-due subscriptions only after the grace period', function (int $daysOverdue, SubscriptionStatus $expected) {
    config(['billing.grace_days' => 7]);
    $plan = Plan::factory()->sellerOf($this->tenant)->paid()->create();
    $subscription = subscriptionFor($plan, SubscriptionStatus::PAST_DUE, ['current_period_ends_at' => now()->subDays($daysOverdue)]);
    $invoice = SubscriptionInvoice::factory()->forSubscription($subscription)->create();

    $this->artisan('billing:process-renewals')->assertSuccessful();

    expect($subscription->fresh()->status)->toBe($expected)
        ->and($invoice->fresh()->status)->toBe($expected === SubscriptionStatus::EXPIRED ? InvoiceStatus::VOID : InvoiceStatus::PENDING);
})->with([
    'within grace' => [6, SubscriptionStatus::PAST_DUE],
    'grace over' => [8, SubscriptionStatus::EXPIRED],
]);

it('expires cancelled subscriptions at their end date', function () {
    $plan = Plan::factory()->sellerOf($this->tenant)->paid()->create();
    $subscription = subscriptionFor($plan, SubscriptionStatus::CANCELLED, ['ends_at' => now()->subMinute(), 'cancelled_at' => now()->subWeek()]);

    $this->artisan('billing:process-renewals')->assertSuccessful();

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::EXPIRED);
});

it('reactivates a past-due subscription when its invoice is paid', function () {
    app(CurrentTenant::class)->set($this->tenant);
    $plan = Plan::factory()->sellerOf($this->tenant)->paid()->create();
    $subscription = subscriptionFor($plan, SubscriptionStatus::PAST_DUE, ['current_period_ends_at' => now()->subDay()]);
    $invoice = SubscriptionInvoice::factory()->forSubscription($subscription)->create([
        'period_starts_at' => now()->subDay(),
        'period_ends_at' => now()->subDay()->addMonthNoOverflow(),
    ]);

    $this->actingAs($subscription->subscriber->owner)
        ->post(route('company.billing.invoices.pay', $invoice))
        ->assertRedirect(route('company.billing.show'));

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::PAID)
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::ACTIVE)
        ->and($subscription->fresh()->current_period_ends_at->toDateString())->toBe(now()->subDay()->addMonthNoOverflow()->toDateString());
});
