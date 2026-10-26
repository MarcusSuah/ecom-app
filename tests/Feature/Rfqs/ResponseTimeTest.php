<?php

use App\Actions\Suppliers\SupplierResponseTime;
use App\Models\Company;
use App\Models\Quote;
use App\Models\Rfq;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * An RFQ notified $daysAgo days ago that the supplier answered after $minutes.
 */
function answeredAfter(Company $supplier, int $minutes, int $daysAgo = 1): void
{
    $notifiedAt = now()->subDays($daysAgo);
    $rfq = Rfq::factory()->create();
    $rfq->recipients()->create(['company_id' => $supplier->id, 'notified_at' => $notifiedAt]);
    Quote::factory()->for($rfq)->for($supplier)->create(['created_at' => $notifiedAt->addMinutes($minutes)]);
}

it('is the median time from notification to quote', function (array $minutes, int $median) {
    $supplier = Company::factory()->supplier()->create();
    foreach ($minutes as $value) {
        answeredAfter($supplier, $value);
    }

    expect(app(SupplierResponseTime::class)->calculate($supplier))->toBe($median);
})->with([
    'one quote' => [[45], 45],
    'odd count' => [[30, 600, 90], 90],
    'even count' => [[30, 90, 120, 600], 105],
]);

it('only counts requests notified in the last 90 days, and requests left unanswered do not count', function () {
    $supplier = Company::factory()->supplier()->create();
    answeredAfter($supplier, 60, daysAgo: 10);
    answeredAfter($supplier, 5000, daysAgo: 91);
    $unanswered = Rfq::factory()->create();
    $unanswered->recipients()->create(['company_id' => $supplier->id, 'notified_at' => now()->subDays(2)]);

    expect(app(SupplierResponseTime::class)->calculate($supplier))->toBe(60);
});

it('is unknown without any quotes', function () {
    expect(app(SupplierResponseTime::class)->calculate(Company::factory()->supplier()->create()))->toBeNull();
});

it('describes the time for buyers', function (int $minutes, string $label) {
    expect(SupplierResponseTime::describe($minutes))->toBe($label);
})->with([
    [20, 'Within 1 hour'],
    [61, 'Within 2 hours'],
    [60 * 30, 'Within 2 days'],
]);

it('shows the response time on the supplier profile', function () {
    $supplier = Company::factory()->supplier()->verified()->create();
    answeredAfter($supplier, 150);

    $this->get(route('suppliers.show', $supplier))
        ->assertInertia(fn (Assert $page) => $page->where('supplier.response_time', 'Within 3 hours'));
});
