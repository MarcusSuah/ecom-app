<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\CurrencyRate;
use App\Models\Product;
use App\Models\Rfq;
use Illuminate\Support\Facades\Notification;

it('gives the product modal the tiers, supplier and stock as JSON', function () {
    $product = Product::factory()->for(Company::factory()->supplier()->verified())->active()->create();

    $this->getJson(route('products.quick-view', $product))
        ->assertOk()
        ->assertJsonPath('product.slug', $product->slug)
        ->assertJsonPath('product.tiers.0.unit_price', 1000)
        ->assertJsonPath('supplier.is_verified', true)
        ->assertJsonPath('url', route('products.show', $product));
});

it('hides unlisted products from the modal', function () {
    $product = Product::factory()->create();

    $this->getJson(route('products.quick-view', $product))->assertNotFound();
});

it('sends the inquiry list as one request per product, to its supplier only, at least at the MOQ', function () {
    Notification::fake();
    $first = Product::factory()->for(Company::factory()->supplier()->verified())->active()->create(['moq' => 100]);
    $second = Product::factory()->for(Company::factory()->supplier()->verified())->active()->create(['moq' => 50, 'category_id' => $first->category_id]);
    $buyer = userWithRole(Role::BUYER);

    $this->actingAs($buyer)->post(route('inquiries.store'), [
        'items' => [['product_id' => $first->id, 'quantity' => 10], ['product_id' => $second->id, 'quantity' => 400]],
        'deliver_city' => 'Kigali',
        'guest_phone' => '+250 788 123 456',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $rfqs = Rfq::query()->with('recipients')->orderBy('id')->get();
    expect($rfqs)->toHaveCount(2)
        ->and($rfqs[0]->quantity)->toBe(100)
        ->and($rfqs[0]->recipients->pluck('company_id')->all())->toBe([$first->company_id])
        ->and($rfqs[1]->quantity)->toBe(400)
        ->and($rfqs[1]->recipients->pluck('company_id')->all())->toBe([$second->company_id])
        ->and($buyer->fresh()->phone)->toBe('+250 788 123 456');
});

it('lets guests send the inquiry list with their contact details', function () {
    Notification::fake();
    $product = Product::factory()->for(Company::factory()->supplier()->verified())->active()->create();
    $items = [['product_id' => $product->id, 'quantity' => 500]];

    $this->post(route('inquiries.store'), ['items' => $items, 'deliver_city' => 'Kigali'])
        ->assertSessionHasErrors(['guest_name', 'guest_email', 'guest_phone']);

    $this->post(route('inquiries.store'), [
        'items' => $items,
        'deliver_city' => 'Kigali',
        'guest_name' => 'Aline',
        'guest_email' => 'aline@example.rw',
        'guest_phone' => '0788123456',
    ])->assertSessionHasNoErrors();

    expect(Rfq::sole())->buyer_id->toBeNull()->guest_email->toBe('aline@example.rw');
});

it('does not send an inquiry to the buyer\'s own company', function () {
    Notification::fake();
    [$owner, $company] = supplierOwner();
    $product = Product::factory()->for($company)->active()->create();

    $this->actingAs($owner)->post(route('inquiries.store'), [
        'items' => [['product_id' => $product->id, 'quantity' => 500]],
        'deliver_city' => 'Kigali',
        'guest_phone' => '0788123456',
    ])->assertSessionHasNoErrors();

    expect(Rfq::count())->toBe(0);
});

it('gives the local order drawer the cart with the amounts it needs for live totals', function () {
    config(['marketplace.road_factor' => 1.0]);
    [$shop, $item] = shopWithItem(price: 2500, stock: 10);
    $customer = customerAtCityCentre();
    $this->actingAs($customer)->post(route('cart.items.store', $item), ['quantity' => 2]);
    $this->actingAs($customer)->put(route('cart.fulfilment', $shop), ['fulfilment' => 'delivery']);

    $this->actingAs($customer)->getJson(route('cart.summary'))
        ->assertOk()
        ->assertJsonPath('groups.0.items.0.unit_price_minor', 2500)
        ->assertJsonPath('groups.0.items.0.quantity', 2)
        ->assertJsonPath('groups.0.subtotal', 'RWF 5,000')
        ->assertJsonPath('groups.0.shop.free_threshold_minor', 20000)
        ->assertJsonPath('groups.0.delivery.standard_fee', 1300);
});

it('shows cart amounts in the chosen currency with the amount charged', function () {
    config(['marketplace.road_factor' => 1.0]);
    CurrencyRate::factory()->rate('1450')->create();
    [$shop, $item] = shopWithItem(price: 14500, stock: 10);
    $customer = customerAtCityCentre();
    $this->actingAs($customer)->post(route('cart.items.store', $item), ['quantity' => 1]);
    $this->actingAs($customer)->put(route('cart.fulfilment', $shop), ['fulfilment' => 'pickup']);
    $this->actingAs($customer)->put(route('currency.update'), ['currency' => 'USD']);

    $this->actingAs($customer)->getJson(route('cart.summary'))
        ->assertJsonPath('groups.0.items.0.unit_price', 'USD 10.00')
        ->assertJsonPath('groups.0.total_listed', 'RWF 14,500');
});
