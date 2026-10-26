<?php

use App\Enums\Fulfilment;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\User;

function unpaidPickupOrder(array $attributes = []): Order
{
    return Order::factory()->status(OrderStatus::PENDING_PAYMENT)->create(['total' => 12000, 'subtotal' => 12000, 'fulfilment' => Fulfilment::PICKUP, ...$attributes]);
}

it('lets the buyer pay a pickup order in cash and the shop take the cash at handover', function () {
    $order = unpaidPickupOrder();
    $seller = $order->seller->owner;

    $this->actingAs($order->buyer)->post(route('orders.pay-cash', $order))->assertSessionHasNoErrors();
    expect($order->fresh()->payment_method)->toBe(PaymentMethod::CASH_ON_PICKUP);

    foreach (['accepted', 'preparing', 'ready_for_pickup'] as $step) {
        $this->actingAs($seller)->patch(route('seller.orders.status', $order), ['status' => $step])->assertSessionHasNoErrors();
    }

    $this->actingAs($seller)->post(route('seller.orders.cash-collected', $order))->assertSessionHasNoErrors();

    $order->refresh();
    $payment = Payment::query()->where('order_id', $order->id)->sole();
    $payout = Payout::query()->where('order_id', $order->id)->sole();
    expect($order->status)->toBe(OrderStatus::COMPLETED)
        ->and($payment->provider)->toBe('cash')
        ->and($payment->status)->toBe(PaymentStatus::RELEASED)
        ->and($payment->amount)->toBe(12000)
        ->and($payout->status)->toBe(PayoutStatus::PAID)
        ->and($payout->net + $payout->commission)->toBe(12000);
});

it('offers cash only for pickup orders that are not already being paid online', function () {
    $delivery = unpaidPickupOrder(['fulfilment' => Fulfilment::DELIVERY]);
    $this->actingAs($delivery->buyer)->post(route('orders.pay-cash', $delivery))->assertForbidden();

    $order = unpaidPickupOrder();
    $this->actingAs(User::factory()->create())->post(route('orders.pay-cash', $order))->assertForbidden();
});

it('does not take the cash before the order is ready for pickup', function () {
    $order = unpaidPickupOrder(['payment_method' => PaymentMethod::CASH_ON_PICKUP]);

    $this->actingAs($order->seller->owner)->post(route('seller.orders.cash-collected', $order))->assertForbidden();
});

it('lets the shop cancel a cash order without a refund, with a reason', function () {
    $order = unpaidPickupOrder(['payment_method' => PaymentMethod::CASH_ON_PICKUP, 'status' => OrderStatus::ACCEPTED]);
    $seller = $order->seller->owner;

    $this->actingAs($seller)->post(route('seller.orders.refund', $order), ['reason' => 'x'])->assertForbidden();
    $this->actingAs($seller)->post(route('seller.orders.cancel', $order), ['reason' => 'Out of flour today'])->assertSessionHasNoErrors();

    expect($order->fresh()->status)->toBe(OrderStatus::CANCELLED)
        ->and(Payment::query()->where('order_id', $order->id)->exists())->toBeFalse();
});
