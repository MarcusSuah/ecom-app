<?php

use App\Enums\Fulfilment;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Cart;
use App\Models\Order;
use App\Models\ShopProduct;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['marketplace.road_factor' => 1.0]);
});

it('splits the cart into one order per shop and decrements stock', function () {
    [$deliveryShop, $milk] = shopWithItem(km: 2.0, price: 2500, stock: 10);
    [$pickupShop, $bread] = shopWithItem(km: 1.0, price: 1200, stock: 5);
    $customer = customerAtCityCentre();

    $this->actingAs($customer)->post(route('cart.items.store', $milk), ['quantity' => 3]);
    $this->actingAs($customer)->post(route('cart.items.store', $bread), ['quantity' => 2]);
    $this->actingAs($customer)->put(route('cart.fulfilment', $pickupShop), ['fulfilment' => 'pickup']);

    $this->actingAs($customer)->post(route('checkout'))->assertRedirect(route('orders.index'));

    $delivery = Order::query()->where('seller_company_id', $deliveryShop->company_id)->sole();
    $pickup = Order::query()->where('seller_company_id', $pickupShop->company_id)->sole();

    expect($delivery)
        ->type->toBe(OrderType::LOCAL)
        ->fulfilment->toBe(Fulfilment::DELIVERY)
        ->status->toBe(OrderStatus::PENDING_PAYMENT)
        ->subtotal->toBe(7500)
        ->delivery_fee->toBe(1300)
        ->total->toBe(8800)
        ->and($delivery->address_snapshot)->toMatchArray(['label' => 'Office', 'landmark' => 'Near the roundabout', 'distance_km' => 2.0])
        ->and($delivery->items()->sole()->only(['name', 'unit_price', 'quantity', 'line_total']))
        ->toBe(['name' => $milk->name, 'unit_price' => 2500, 'quantity' => 3, 'line_total' => 7500]);

    expect($pickup)
        ->fulfilment->toBe(Fulfilment::PICKUP)
        ->delivery_fee->toBe(0)
        ->total->toBe(2400)
        ->address_snapshot->toBeNull();

    expect($milk->fresh()->stock)->toBe(7)
        ->and($bread->fresh()->stock)->toBe(3)
        ->and(Cart::query()->where('user_id', $customer->id)->sole()->items()->count())->toBe(0);
});

it('delivers free once the shop subtotal reaches the threshold', function () {
    [, $item] = shopWithItem(price: 10000);
    $customer = customerAtCityCentre();
    $this->actingAs($customer)->post(route('cart.items.store', $item), ['quantity' => 2]);

    $this->actingAs($customer)->post(route('checkout'));

    expect(Order::sole())->delivery_fee->toBe(0)->total->toBe(20000);
});

it('places nothing when an item ran out of stock after it was added', function () {
    [, $first] = shopWithItem(stock: 10);
    [, $second] = shopWithItem(stock: 2);
    $customer = customerAtCityCentre();
    $this->actingAs($customer)->post(route('cart.items.store', $first), ['quantity' => 1]);
    $this->actingAs($customer)->post(route('cart.items.store', $second), ['quantity' => 2]);
    $second->update(['stock' => 1]);

    $this->actingAs($customer)->post(route('checkout'))->assertSessionHasErrors('cart');

    expect(Order::count())->toBe(0)
        ->and($first->fresh()->stock)->toBe(10);
});

it('does not deliver outside the shop\'s radius', function () {
    [$shop, $item] = shopWithItem(km: 8.0, shopAttributes: ['delivery_radius_km' => 5]);
    $customer = customerAtCityCentre();
    $this->actingAs($customer)->post(route('cart.items.store', $item), ['quantity' => 1]);

    $this->actingAs($customer)->post(route('checkout'))->assertSessionHasErrors('cart');
    expect(Order::count())->toBe(0);

    $this->actingAs($customer)->put(route('cart.fulfilment', $shop), ['fulfilment' => 'pickup']);
    $this->actingAs($customer)->post(route('checkout'))->assertSessionHasNoErrors();
    expect(Order::sole()->fulfilment)->toBe(Fulfilment::PICKUP);
});

it('needs a location for delivery orders', function () {
    [, $item] = shopWithItem();
    $customer = User::factory()->create();
    $this->actingAs($customer)->post(route('cart.items.store', $item), ['quantity' => 1]);

    $this->actingAs($customer)->post(route('checkout'))->assertSessionHasErrors('cart');
});

it('refuses fulfilment a shop does not offer', function () {
    [$shop, $item] = shopWithItem(shopAttributes: ['offers_delivery' => false]);
    $customer = customerAtCityCentre();
    $this->actingAs($customer)->post(route('cart.items.store', $item), ['quantity' => 1]);

    $this->actingAs($customer)->put(route('cart.fulfilment', $shop), ['fulfilment' => 'delivery'])->assertSessionHasErrors('fulfilment');
});

it('does not add more than is in stock, hidden items or the customer\'s own items', function () {
    [, $item] = shopWithItem(stock: 2);
    $hidden = ShopProduct::factory()->hidden()->create();
    $customer = customerAtCityCentre();

    $this->actingAs($customer)->post(route('cart.items.store', $item), ['quantity' => 3])
        ->assertSessionHasErrors(['quantity' => 'Only 2 left in stock.']);
    $this->actingAs($customer)->post(route('cart.items.store', $hidden), ['quantity' => 1])
        ->assertSessionHasErrors(['quantity' => 'This item is no longer available.']);
    $this->actingAs($item->shop->company->owner)->post(route('cart.items.store', $item), ['quantity' => 1])
        ->assertSessionHasErrors(['quantity' => 'You cannot order from your own shop.']);
});

it('shows the cart grouped by shop with live delivery fees', function () {
    [, $item] = shopWithItem(km: 3.0, price: 1000);
    $customer = customerAtCityCentre();
    $this->actingAs($customer)->post(route('cart.items.store', $item), ['quantity' => 2]);

    $this->actingAs($customer)->get(route('cart.show'))->assertInertia(fn (Assert $page) => $page
        ->component('cart/show')
        ->has('groups', 1)
        ->where('groups.0.fulfilment', 'delivery')
        ->where('groups.0.subtotal', 'RWF 2,000')
        ->where('groups.0.delivery.formatted_fee', 'RWF 1,600')
        ->where('groups.0.total', 'RWF 3,600')
        ->where('cartCount', 2));
});

it('requires signing in to use the cart', function () {
    [, $item] = shopWithItem();

    $this->post(route('cart.items.store', $item), ['quantity' => 1])->assertRedirect(route('login'));
});
