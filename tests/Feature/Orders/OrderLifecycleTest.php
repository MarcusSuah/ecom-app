<?php

use App\Actions\Orders\InvalidOrderTransition;
use App\Actions\Orders\OrderStateMachine;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Company;
use App\Models\Order;
use App\Models\Shop;
use App\Models\ShopProduct;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\OrderStatusChanged;
use App\Services\Sms\SmsSender;
use App\Support\CurrentTenant;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * An order for 3 of a shop item (stock already decremented to 7).
 *
 * @return array{0: Order, 1: ShopProduct}
 */
function orderWithItem(OrderStatus $status, bool $delivery = false, OrderType $type = OrderType::LOCAL): array
{
    $shop = Shop::factory()->create();
    $product = ShopProduct::factory()->for($shop)->create(['stock' => 7]);
    $order = Order::factory()->for($shop->company, 'seller')->status($status)
        ->state(['type' => $type, ...($delivery || $type === OrderType::WHOLESALE ? ['fulfilment' => 'delivery'] : [])])
        ->create();
    $order->items()->make()->forceFill([
        'purchasable_type' => $product->getMorphClass(), 'purchasable_id' => $product->id,
        'name' => $product->name, 'unit_price' => $product->price, 'quantity' => 3, 'line_total' => $product->price * 3,
    ])->save();

    return [$order, $product];
}

it('moves each kind of order through its own steps', function (OrderType $type, bool $delivery, array $steps) {
    [$order] = orderWithItem(OrderStatus::PAID, $delivery, $type);
    $owner = $order->seller->owner;

    foreach ($steps as $status) {
        $this->actingAs($owner)->patch(route('seller.orders.status', $order), ['status' => $status->value])->assertSessionHasNoErrors();
        expect($order->fresh()->status)->toBe($status);
    }
})->with([
    'local pickup' => [OrderType::LOCAL, false, [OrderStatus::ACCEPTED, OrderStatus::PREPARING, OrderStatus::READY_FOR_PICKUP, OrderStatus::DELIVERED]],
    'local delivery' => [OrderType::LOCAL, true, [OrderStatus::ACCEPTED, OrderStatus::PREPARING, OrderStatus::OUT_FOR_DELIVERY, OrderStatus::DELIVERED]],
    'wholesale' => [OrderType::WHOLESALE, true, [OrderStatus::IN_PRODUCTION, OrderStatus::SHIPPED, OrderStatus::DELIVERED]],
]);

it('rejects invalid transitions', function (OrderType $type, bool $delivery, OrderStatus $from, OrderStatus $to) {
    [$order] = orderWithItem($from, $delivery, $type);

    expect(fn () => app(OrderStateMachine::class)->transition($order, $to))->toThrow(InvalidOrderTransition::class);
    expect($order->fresh()->status)->toBe($from);
})->with([
    'skip accepting' => [OrderType::LOCAL, false, OrderStatus::PAID, OrderStatus::PREPARING],
    'pickup order out for delivery' => [OrderType::LOCAL, false, OrderStatus::PREPARING, OrderStatus::OUT_FOR_DELIVERY],
    'delivery order ready for pickup' => [OrderType::LOCAL, true, OrderStatus::PREPARING, OrderStatus::READY_FOR_PICKUP],
    'local step on a wholesale order' => [OrderType::WHOLESALE, true, OrderStatus::PAID, OrderStatus::ACCEPTED],
    'wholesale step on a local order' => [OrderType::LOCAL, true, OrderStatus::PAID, OrderStatus::IN_PRODUCTION],
    'unpaid straight to accepted' => [OrderType::LOCAL, false, OrderStatus::PENDING_PAYMENT, OrderStatus::ACCEPTED],
    'cancel a paid order' => [OrderType::LOCAL, false, OrderStatus::PAID, OrderStatus::CANCELLED],
    'refund an unpaid order' => [OrderType::LOCAL, false, OrderStatus::PENDING_PAYMENT, OrderStatus::REFUNDED],
    'reopen a completed order' => [OrderType::LOCAL, false, OrderStatus::COMPLETED, OrderStatus::PREPARING],
    'refund a completed order' => [OrderType::WHOLESALE, true, OrderStatus::COMPLETED, OrderStatus::REFUNDED],
]);

it('shows an invalid seller move as an error on the page', function () {
    [$order] = orderWithItem(OrderStatus::PAID);

    $this->actingAs($order->seller->owner)
        ->patch(route('seller.orders.status', $order), ['status' => 'preparing'])
        ->assertSessionHasErrors(['status' => 'An order that is Paid cannot become Preparing.']);
});

it('does not let sellers complete, refund or mark orders paid through the status buttons', function (OrderStatus $status, OrderStatus $from) {
    [$order] = orderWithItem($from);

    $this->actingAs($order->seller->owner)->patch(route('seller.orders.status', $order), ['status' => $status->value])->assertForbidden();
})->with([
    'complete' => [OrderStatus::COMPLETED, OrderStatus::DELIVERED],
    'refund' => [OrderStatus::REFUNDED, OrderStatus::PAID],
]);

it('writes every change to the order history', function () {
    [$order] = orderWithItem(OrderStatus::PAID);
    $owner = $order->seller->owner;

    app(OrderStateMachine::class)->transition($order, OrderStatus::ACCEPTED, $owner, 'On it');

    expect($order->history()->sole())
        ->from_status->toBe(OrderStatus::PAID)
        ->to_status->toBe(OrderStatus::ACCEPTED)
        ->changed_by->toBe($owner->id)
        ->note->toBe('On it');
});

it('tells buyer and seller of every change, by SMS when paid and when out for delivery', function () {
    Notification::fake();
    $sms = $this->mock(SmsSender::class);
    $sms->shouldReceive('send')->once()->withArgs(fn (string $to, string $text) => $to === '+250788000111' && str_contains($text, 'on its way'));

    [$order] = orderWithItem(OrderStatus::PREPARING, delivery: true);
    $order->buyer->forceFill(['phone' => '+250788000111'])->save();

    app(OrderStateMachine::class)->transition($order, OrderStatus::OUT_FOR_DELIVERY);

    Notification::assertSentTo([$order->buyer, $order->seller->owner], OrderStatusChanged::class, fn ($notification, array $channels) => $channels === ['database', 'mail']);
});

it('puts local stock back when an order is cancelled or refunded', function (OrderStatus $from, OrderStatus $to) {
    [$order, $product] = orderWithItem($from);

    app(OrderStateMachine::class)->transition($order, $to);

    expect($product->fresh()->stock)->toBe(10);
})->with([
    'cancel unpaid' => [OrderStatus::PENDING_PAYMENT, OrderStatus::CANCELLED],
    'refund paid' => [OrderStatus::PREPARING, OrderStatus::REFUNDED],
]);

it('lets the buyer cancel only an unpaid order', function (OrderStatus $status, bool $allowed) {
    [$order] = orderWithItem($status);

    $response = $this->actingAs($order->buyer)->post(route('orders.cancel', $order));

    $allowed ? $response->assertSessionHasNoErrors() : $response->assertForbidden();
    expect($order->fresh()->status)->toBe($allowed ? OrderStatus::CANCELLED : $status);
})->with([
    'unpaid' => [OrderStatus::PENDING_PAYMENT, true],
    'paid' => [OrderStatus::PAID, false],
]);

it('keeps orders between the buyer and the seller', function () {
    [$order] = orderWithItem(OrderStatus::PAID);
    $stranger = User::factory()->create();
    $otherSeller = Company::factory()->shop()->create()->owner;

    $this->actingAs($stranger)->get(route('orders.show', $order))->assertForbidden();
    $this->actingAs($otherSeller)->patch(route('seller.orders.status', $order), ['status' => 'accepted'])->assertForbidden();
    $this->actingAs($order->buyer)->patch(route('seller.orders.status', $order), ['status' => 'accepted'])->assertForbidden();

    $this->actingAs($order->buyer)->get(route('orders.show', $order))->assertInertia(fn (Assert $page) => $page->component('orders/show'));
    $this->actingAs($order->seller->owner)->get(route('seller.orders.show', $order))->assertInertia(fn (Assert $page) => $page
        ->component('seller/orders/show')
        ->where('order.seller_steps.0.value', 'accepted'));
});

it('hides other marketplaces\' orders', function () {
    $foreign = app(CurrentTenant::class)->runAs(Tenant::factory()->create(), fn () => orderWithItem(OrderStatus::PAID)[0]);

    $this->actingAs($foreign->buyer)->get(route('orders.show', $foreign))->assertNotFound();
});

it('keeps unpaid orders out of the seller\'s list', function () {
    [$paid] = orderWithItem(OrderStatus::PAID);
    Order::factory()->for($paid->seller, 'seller')->status(OrderStatus::PENDING_PAYMENT)->create();

    $this->actingAs($paid->seller->owner)->get(route('seller.orders.index'))->assertInertia(fn (Assert $page) => $page
        ->has('orders.data', 1)
        ->where('orders.data.0.id', $paid->id));
});
