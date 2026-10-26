<?php

use App\Enums\Fulfilment;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\QuoteStatus;
use App\Enums\RfqStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Rfq;
use App\Models\User;

function acceptedQuote(array $quoteAttributes = []): Quote
{
    $rfq = Rfq::factory()->status(RfqStatus::AWARDED)->create(['quantity' => 1500, 'deliver_city' => 'Huye']);

    return Quote::factory()->for($rfq)->status(QuoteStatus::ACCEPTED)->tiers([[100, 1000], [1000, 900], [5000, 700]])->create($quoteAttributes);
}

it('creates a wholesale order from an accepted quote at its tier price', function () {
    $quote = acceptedQuote();
    $rfq = $quote->rfq;

    $this->actingAs($rfq->buyer)->post(route('rfqs.quotes.order', ['rfq' => $rfq, 'quote' => $quote]))->assertRedirect();

    $order = Order::sole();
    expect($order)
        ->type->toBe(OrderType::WHOLESALE)
        ->fulfilment->toBe(Fulfilment::DELIVERY)
        ->status->toBe(OrderStatus::PENDING_PAYMENT)
        ->seller_company_id->toBe($quote->company_id)
        ->quote_id->toBe($quote->id)
        ->total->toBe(1_350_000)
        ->and($order->address_snapshot['label'])->toBe('Huye')
        ->and($order->items()->sole()->only(['name', 'unit_price', 'quantity']))->toBe(['name' => $rfq->title, 'unit_price' => 900, 'quantity' => 1500]);
});

it('creates one order per quote', function () {
    $quote = acceptedQuote();
    $rfq = $quote->rfq;

    $this->actingAs($rfq->buyer)->post(route('rfqs.quotes.order', ['rfq' => $rfq, 'quote' => $quote]));
    $this->actingAs($rfq->buyer)->post(route('rfqs.quotes.order', ['rfq' => $rfq, 'quote' => $quote]))
        ->assertSessionHasErrors(['quote' => 'An order has already been placed for this quote.']);

    expect(Order::count())->toBe(1);
});

it('refuses quotes that are not accepted, expired or someone else\'s', function () {
    $submitted = acceptedQuote(['status' => QuoteStatus::SUBMITTED]);
    $expired = acceptedQuote(['valid_until' => now()->subDay()]);
    $theirs = acceptedQuote();

    $this->actingAs($submitted->rfq->buyer)->post(route('rfqs.quotes.order', ['rfq' => $submitted->rfq, 'quote' => $submitted]))->assertSessionHasErrors('quote');
    $this->actingAs($expired->rfq->buyer)->post(route('rfqs.quotes.order', ['rfq' => $expired->rfq, 'quote' => $expired]))
        ->assertSessionHasErrors(['quote' => 'This quote has expired. Ask the supplier for a new one.']);
    $this->actingAs(User::factory()->create())->post(route('rfqs.quotes.order', ['rfq' => $theirs->rfq, 'quote' => $theirs]))->assertForbidden();

    expect(Order::count())->toBe(0);
});

it('orders straight from a product page at its tier price', function () {
    $product = Product::factory()->active()->tiers([[100, 1000], [500, 900]])->create(['moq' => 100]);

    $this->actingAs(User::factory()->create())
        ->post(route('products.order', $product), ['quantity' => 600, 'deliver_to' => 'Musanze'])
        ->assertRedirect();

    expect(Order::sole())
        ->type->toBe(OrderType::WHOLESALE)
        ->total->toBe(540000)
        ->seller_company_id->toBe($product->company_id);
});

it('refuses product orders below the MOQ or for the seller\'s own product', function () {
    $product = Product::factory()->active()->create(['moq' => 100]);

    $this->actingAs(User::factory()->create())->post(route('products.order', $product), ['quantity' => 99, 'deliver_to' => 'Kigali'])
        ->assertSessionHasErrors(['quantity' => 'The minimum order is 100 piece.']);
    $this->actingAs($product->company->owner)->post(route('products.order', $product), ['quantity' => 200, 'deliver_to' => 'Kigali'])
        ->assertSessionHasErrors(['quantity' => 'You cannot order your own product.']);

    expect(Order::count())->toBe(0);
});
