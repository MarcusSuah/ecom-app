<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Tenant;
use App\Support\CurrentTenant;

function unpaidOrder(): Order
{
    return Order::factory()->status(OrderStatus::PENDING_PAYMENT)->create(['total' => 12000, 'subtotal' => 12000]);
}

function fakeWebhook(array $payload, ?string $signature = null)
{
    $body = json_encode($payload);
    $signature ??= hash_hmac('sha256', $body, config('payments.providers.fake.webhook_secret'));

    return test()->call('POST', route('webhooks.payments', ['provider' => 'fake']), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_FAKE_SIGNATURE' => $signature,
    ], $body);
}

it('holds a successful payment and marks the order paid', function () {
    $order = unpaidOrder();

    $this->actingAs($order->buyer)->post(route('orders.pay', $order), ['provider' => 'fake'])->assertSessionHasNoErrors();

    $payment = Payment::sole();
    expect($payment)
        ->status->toBe(PaymentStatus::HELD)
        ->amount->toBe(12000)
        ->held_at->not->toBeNull()
        ->and($order->fresh()->status)->toBe(OrderStatus::PAID)
        ->and($order->history()->latest('id')->first()->to_status)->toBe(OrderStatus::PAID);
});

it('only lets the buyer pay, and only once', function () {
    $order = unpaidOrder();

    $this->actingAs($order->seller->owner)->post(route('orders.pay', $order), ['provider' => 'fake'])->assertForbidden();
    $this->actingAs($order->buyer)->post(route('orders.pay', $order), ['provider' => 'fake']);
    $this->actingAs($order->buyer)->post(route('orders.pay', $order), ['provider' => 'fake'])->assertForbidden();

    expect(Payment::count())->toBe(1);
});

it('refuses providers that are not enabled and needs a phone for Mobile Money', function () {
    $order = unpaidOrder();
    config(['payments.enabled' => ['fake', 'mtn_momo']]);

    $this->actingAs($order->buyer)->post(route('orders.pay', $order), ['provider' => 'flutterwave'])->assertSessionHasErrors('provider');
    $this->actingAs($order->buyer)->post(route('orders.pay', $order), ['provider' => 'mtn_momo'])
        ->assertSessionHasErrors(['phone' => 'Enter the Mobile Money number to charge.']);
});

it('rejects webhooks with a bad signature', function () {
    $payment = Payment::factory()->forOrder(unpaidOrder())->initiated()->create();

    fakeWebhook(['reference' => $payment->reference, 'status' => 'succeeded'], 'forged')->assertUnauthorized();

    expect($payment->fresh()->status)->toBe(PaymentStatus::INITIATED);
});

it('applies a webhook once however often it is delivered', function () {
    $order = unpaidOrder();
    $payment = Payment::factory()->forOrder($order)->initiated()->create();
    $payload = ['reference' => $payment->reference, 'status' => 'succeeded', 'provider_reference' => 'txn-1'];

    fakeWebhook($payload)->assertOk();
    $heldAt = $payment->fresh()->held_at;

    $this->travel(5)->minutes();
    fakeWebhook($payload)->assertOk();
    fakeWebhook([...$payload, 'status' => 'failed'])->assertOk();

    expect($payment->fresh())
        ->status->toBe(PaymentStatus::HELD)
        ->held_at->equalTo($heldAt)->toBeTrue()
        ->and($order->history()->where('to_status', OrderStatus::PAID)->count())->toBe(1);
});

it('marks a failed payment and leaves the order waiting', function () {
    $order = unpaidOrder();
    $payment = Payment::factory()->forOrder($order)->initiated()->create();

    fakeWebhook(['reference' => $payment->reference, 'status' => 'failed'])->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::FAILED)
        ->and($order->fresh()->status)->toBe(OrderStatus::PENDING_PAYMENT);
});

it('refunds a second successful payment for an order already paid', function () {
    $order = unpaidOrder();
    $first = Payment::factory()->forOrder($order)->initiated()->create();
    $second = Payment::factory()->forOrder($order)->initiated()->create();

    fakeWebhook(['reference' => $first->reference, 'status' => 'succeeded']);
    fakeWebhook(['reference' => $second->reference, 'status' => 'succeeded']);

    expect($first->fresh()->status)->toBe(PaymentStatus::HELD)
        ->and($second->fresh()->status)->toBe(PaymentStatus::REFUNDED);
});

it('finds payments of any marketplace from the one webhook URL', function () {
    $foreign = app(CurrentTenant::class)->runAs(Tenant::factory()->create(), function () {
        $order = unpaidOrder();

        return Payment::factory()->forOrder($order)->initiated()->create();
    });

    fakeWebhook(['reference' => $foreign->reference, 'status' => 'succeeded'])->assertOk();

    expect($foreign->fresh()->status)->toBe(PaymentStatus::HELD)
        ->and(Order::withoutGlobalScopes()->find($foreign->order_id)->status)->toBe(OrderStatus::PAID);
});

it('ignores webhooks for unknown payments or providers', function () {
    fakeWebhook(['reference' => 'nope', 'status' => 'succeeded'])->assertNotFound();
    $this->postJson(route('webhooks.payments', ['provider' => 'paypal']), [])->assertNotFound();
});
