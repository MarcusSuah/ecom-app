<?php

use App\Models\CartItem;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['marketplace.road_factor' => 1.0]);
});

it('keeps the local order while the buyer is active, with when it would be emptied', function () {
    [, $item] = shopWithItem(stock: 10);
    $customer = customerAtCityCentre();
    $this->actingAs($customer)->post(route('cart.items.store', $item), ['quantity' => 2]);

    $this->travel(9)->minutes();

    $this->actingAs($customer)->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('cartCount', 2)
        ->whereNot('cartExpiresAt', null));
});

it('empties a local order left 10 minutes without a change', function () {
    [, $item] = shopWithItem(stock: 10);
    $customer = customerAtCityCentre();
    $this->actingAs($customer)->post(route('cart.items.store', $item), ['quantity' => 2]);

    $this->travel(11)->minutes();

    $this->actingAs($customer)->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('cartCount', 0)
        ->where('cartExpiresAt', null));
    expect(CartItem::query()->count())->toBe(0);
});

it('starts the 10 minutes again whenever the order changes', function () {
    [, $item] = shopWithItem(stock: 10);
    $customer = customerAtCityCentre();
    $this->actingAs($customer)->post(route('cart.items.store', $item), ['quantity' => 1]);

    $this->travel(8)->minutes();
    $this->actingAs($customer)->patch(route('cart.items.update', $item), ['quantity' => 3]);
    $this->travel(8)->minutes();

    $this->actingAs($customer)->getJson(route('cart.summary'))->assertJsonPath('groups.0.items.0.quantity', 3);
});
