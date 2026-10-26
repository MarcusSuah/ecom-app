<?php

use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Notifications\StockRunningLow;
use Illuminate\Support\Facades\Notification;

it('tells the supplier once when orders take a product into low and then out of stock', function () {
    Notification::fake();
    $owner = User::factory()->create();
    $product = Product::factory()->for(Company::factory()->for($owner, 'owner')->supplier()->verified())->active()->create(['moq' => 100, 'stock' => 1000]);
    $buyer = User::factory()->create();
    $order = fn (int $quantity) => $this->actingAs($buyer)->post(route('products.order', $product), ['quantity' => $quantity, 'deliver_to' => 'Kigali'])->assertSessionHasNoErrors();

    // 1000 → 800: still in stock.
    $order(200);
    Notification::assertNothingSent();

    // 800 → 250: low (under three MOQs), then 250 → 150: still low, no second alert.
    $order(550);
    $order(100);
    Notification::assertSentToTimes($owner, StockRunningLow::class, 1);

    // 150 → 50: below the MOQ, so out of stock.
    $order(100);
    Notification::assertSentToTimes($owner, StockRunningLow::class, 2);
    Notification::assertSentTo($owner, fn (StockRunningLow $notification) => $notification->toArray($owner)['title'] === "{$product->name} is out of stock");
});

it('tells the shop when checkout runs an item low', function () {
    Notification::fake();
    config(['marketplace.road_factor' => 1.0]);
    [$shop, $item] = shopWithItem(stock: 6);
    $customer = customerAtCityCentre();

    $this->actingAs($customer)->post(route('cart.items.store', $item), ['quantity' => 2]);
    $this->actingAs($customer)->put(route('cart.fulfilment', $shop), ['fulfilment' => 'pickup']);
    $this->actingAs($customer)->post(route('checkout'))->assertSessionHasNoErrors();

    Notification::assertSentTo($shop->company->owner, StockRunningLow::class, fn (StockRunningLow $notification) => $notification->listing->is($item));
});
