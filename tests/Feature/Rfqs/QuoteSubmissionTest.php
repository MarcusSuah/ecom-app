<?php

use App\Enums\PlanLimit;
use App\Enums\QuoteStatus;
use App\Enums\RfqStatus;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\Rfq;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Companies quote within their seller plan; an unlimited default keeps limits out of the way.
    Plan::factory()->sellerOf($this->tenant)->default()->create();
});

function quotePayload(array $overrides = []): array
{
    return [
        'currency' => 'RWF',
        'lead_time_min_days' => 5,
        'lead_time_max_days' => 10,
        'sample_terms' => 'Two free samples.',
        'valid_until' => now()->addMonth()->toDateString(),
        'message' => 'Happy to help.',
        'tiers' => [
            ['min_qty' => 500, 'unit_price' => '950'],
            ['min_qty' => 2000, 'unit_price' => '900'],
        ],
        ...$overrides,
    ];
}

it('lets a recipient send one quote and edit it until the buyer decides', function () {
    $supplier = Company::factory()->supplier()->verified()->create();
    $rfq = Rfq::factory()->sentTo($supplier)->create(['quantity' => 1000]);

    $this->actingAs($supplier->owner)->put(route('supplier.rfqs.quote', $rfq), quotePayload())->assertSessionHasNoErrors();
    $this->actingAs($supplier->owner)
        ->put(route('supplier.rfqs.quote', $rfq), quotePayload(['tiers' => [['min_qty' => 1000, 'unit_price' => '880']]]))
        ->assertSessionHasNoErrors();

    $quote = Quote::sole();
    expect($quote->company_id)->toBe($supplier->id)
        ->and($quote->status)->toBe(QuoteStatus::SUBMITTED)
        ->and($quote->tiers->map->only(['min_qty', 'unit_price'])->all())->toBe([['min_qty' => 1000, 'unit_price' => 880]])
        ->and($rfq->recipientFor($supplier)->viewed_at)->not->toBeNull();
});

it('does not let a supplier quote on a request it did not receive', function () {
    $outsider = Company::factory()->supplier()->verified()->create();
    $rfq = Rfq::factory()->sentTo(Company::factory()->supplier()->verified()->create())->create();

    $this->actingAs($outsider->owner)->put(route('supplier.rfqs.quote', $rfq), quotePayload())->assertForbidden();
    $this->actingAs($outsider->owner)->get(route('supplier.rfqs.show', $rfq))->assertForbidden();

    expect(Quote::count())->toBe(0);
});

it('hides other marketplaces\' requests from suppliers', function () {
    $supplier = Company::factory()->supplier()->verified()->create();
    $foreign = app(CurrentTenant::class)->runAs(Tenant::factory()->create(), fn () => Rfq::factory()->create());

    $this->actingAs($supplier->owner)->get(route('supplier.rfqs.show', $foreign))->assertNotFound();
});

it('requires the first tier to start at or below the requested quantity', function () {
    $supplier = Company::factory()->supplier()->verified()->create();
    $rfq = Rfq::factory()->sentTo($supplier)->create(['quantity' => 1000]);

    $this->actingAs($supplier->owner)
        ->put(route('supplier.rfqs.quote', $rfq), quotePayload(['tiers' => [['min_qty' => 1500, 'unit_price' => '900']]]))
        ->assertSessionHasErrors(['tiers.0.min_qty' => 'The first tier must start at or below the requested quantity (1,000).']);
});

it('applies the price tier rules to quotes', function () {
    $supplier = Company::factory()->supplier()->verified()->create();
    $rfq = Rfq::factory()->sentTo($supplier)->create(['quantity' => 1000]);

    $this->actingAs($supplier->owner)
        ->put(route('supplier.rfqs.quote', $rfq), quotePayload(['tiers' => [['min_qty' => 500, 'unit_price' => '900'], ['min_qty' => 400, 'unit_price' => '950']]]))
        ->assertSessionHasErrors([
            'tiers.1.min_qty' => 'Each tier must start at a higher quantity than the one before.',
            'tiers.1.unit_price' => 'The unit price cannot be higher than the tier before.',
        ]);
});

it('refuses quotes once the request is closed or expired', function (array $rfqState) {
    $supplier = Company::factory()->supplier()->verified()->create();
    $rfq = Rfq::factory()->sentTo($supplier)->create($rfqState);

    $this->actingAs($supplier->owner)
        ->put(route('supplier.rfqs.quote', $rfq), quotePayload())
        ->assertSessionHasErrors(['rfq' => 'This request is no longer accepting quotes.']);
})->with([
    'awarded' => [fn () => ['status' => RfqStatus::AWARDED]],
    'expired' => [fn () => ['expires_at' => now()->subMinute()]],
]);

it('counts new quotes against the seller plan\'s monthly limit', function () {
    $supplier = Company::factory()->supplier()->verified()->create();
    $limited = Plan::factory()->sellerOf($this->tenant)->limit(PlanLimit::MONTHLY_QUOTES, 1)->create();
    $supplier->subscriptions()->create([
        'tenant_id' => $this->tenant->id, 'plan_id' => $limited->id, 'status' => 'active',
        'price' => 0, 'currency' => 'RWF', 'interval' => 'month',
        'current_period_starts_at' => now()->subYear(), 'current_period_ends_at' => now()->addYear(),
    ]);
    Quote::factory()->for($supplier)->create();

    $this->actingAs($supplier->owner)
        ->put(route('supplier.rfqs.quote', Rfq::factory()->sentTo($supplier)->create()), quotePayload())
        ->assertSessionHasErrors(['rfq' => 'Your plan allows 1 quotes a month. Upgrade your plan to send more.']);

    // A new month starts a new allowance.
    $this->travelTo(now()->addMonthNoOverflow()->startOfMonth()->addHour());
    $this->actingAs($supplier->owner)
        ->put(route('supplier.rfqs.quote', Rfq::factory()->sentTo($supplier)->create()), quotePayload())
        ->assertSessionHasNoErrors();
});

it('asks for a seller plan before the first quote when the marketplace has none', function () {
    Plan::query()->forSellersOf($this->tenant)->delete();
    $supplier = Company::factory()->supplier()->verified()->create();

    $this->actingAs($supplier->owner)
        ->put(route('supplier.rfqs.quote', Rfq::factory()->sentTo($supplier)->create()), quotePayload())
        ->assertSessionHasErrors(['rfq' => 'Choose a seller plan before sending quotes.']);
});

it('lets a supplier decline with a reason, and quote later anyway', function () {
    $supplier = Company::factory()->supplier()->verified()->create();
    $rfq = Rfq::factory()->sentTo($supplier)->create();

    $this->actingAs($supplier->owner)->post(route('supplier.rfqs.decline', $rfq), [])
        ->assertSessionHasErrors(['reason' => 'Tell the buyer why you are declining.']);
    $this->actingAs($supplier->owner)->post(route('supplier.rfqs.decline', $rfq), ['reason' => 'Out of stock']);

    $recipient = $rfq->recipientFor($supplier);
    expect($recipient->declined_at)->not->toBeNull()->and($recipient->decline_reason)->toBe('Out of stock');

    $this->actingAs($supplier->owner)->put(route('supplier.rfqs.quote', $rfq), quotePayload())->assertSessionHasNoErrors();
    expect($recipient->fresh()->declined_at)->toBeNull();
});

it('marks a request viewed when the supplier opens it', function () {
    $supplier = Company::factory()->supplier()->verified()->create();
    $rfq = Rfq::factory()->sentTo($supplier)->create();

    $this->actingAs($supplier->owner)
        ->get(route('supplier.rfqs.show', $rfq))
        ->assertInertia(fn (Assert $page) => $page->component('supplier/rfqs/show')->where('rfq.is_open', true));

    expect($rfq->recipientFor($supplier)->viewed_at)->not->toBeNull();

    $this->actingAs($supplier->owner)
        ->get(route('supplier.rfqs.index'))
        ->assertInertia(fn (Assert $page) => $page->component('supplier/rfqs/index')->where('requests.data.0.is_new', false));
});

it('lets a supplier withdraw and resend a quote', function () {
    $supplier = Company::factory()->supplier()->verified()->create();
    $rfq = Rfq::factory()->sentTo($supplier)->create();
    $quote = Quote::factory()->for($rfq)->for($supplier)->create();

    $this->actingAs($supplier->owner)->post(route('supplier.rfqs.quote.withdraw', $rfq));
    expect($quote->fresh()->status)->toBe(QuoteStatus::WITHDRAWN);

    $this->actingAs($supplier->owner)->put(route('supplier.rfqs.quote', $rfq), quotePayload())->assertSessionHasNoErrors();
    expect($quote->fresh()->status)->toBe(QuoteStatus::SUBMITTED)->and(Quote::count())->toBe(1);
});
