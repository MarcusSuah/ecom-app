<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentGateways;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    config([
        'payments.enabled' => ['fake', 'mtn_momo', 'flutterwave'],
        'payments.providers.mtn_momo' => [
            'base_url' => 'https://momo.test', 'environment' => 'sandbox', 'currency_override' => null,
            'collection_user_id' => 'user', 'collection_api_key' => 'key', 'collection_subscription_key' => 'sub',
            'disbursement_user_id' => 'duser', 'disbursement_api_key' => 'dkey', 'disbursement_subscription_key' => 'dsub',
        ],
        'payments.providers.flutterwave' => ['base_url' => 'https://flw.test', 'secret_key' => 'FLWSECK_TEST', 'webhook_hash' => 'my-secret-hash'],
    ]);
});

function momoOrder(): Order
{
    return Order::factory()->status(OrderStatus::PENDING_PAYMENT)->create(['total' => 15000, 'subtotal' => 15000]);
}

it('asks MoMo to collect from the payer\'s phone and waits for the callback', function () {
    Http::fake([
        'momo.test/collection/token/' => Http::response(['access_token' => 'token-123', 'expires_in' => 3600]),
        'momo.test/collection/v1_0/requesttopay' => Http::response(null, 202),
    ]);
    $order = momoOrder();

    $this->actingAs($order->buyer)->post(route('orders.pay', $order), ['provider' => 'mtn_momo', 'phone' => '+250788123456'])->assertSessionHasNoErrors();

    $payment = Payment::sole();
    expect($payment->status)->toBe(PaymentStatus::INITIATED)->and($order->fresh()->status)->toBe(OrderStatus::PENDING_PAYMENT);

    Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://momo.test/collection/v1_0/requesttopay'
        && $request->hasHeader('Authorization', 'Bearer token-123')
        && $request->hasHeader('X-Reference-Id', $payment->reference)
        && $request['amount'] === '15000'
        && $request['payer']['partyId'] === '250788123456'
        && str_contains($request->header('X-Callback-Url')[0], 'signature='));
});

it('confirms a MoMo callback with the API before trusting it', function () {
    Http::fake([
        'momo.test/collection/token/' => Http::response(['access_token' => 'token-123']),
        'momo.test/collection/v1_0/requesttopay/*' => Http::response(['status' => 'SUCCESSFUL', 'financialTransactionId' => '987']),
    ]);
    $order = momoOrder();
    $payment = Payment::factory()->forOrder($order)->initiated()->create(['provider' => 'mtn_momo', 'provider_reference' => null]);
    $callback = URL::signedRoute('webhooks.payments', ['provider' => 'mtn_momo', 'reference' => $payment->reference]);

    $this->putJson($callback, ['status' => 'SUCCESSFUL'])->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::HELD)->and($order->fresh()->status)->toBe(OrderStatus::PAID);
});

it('rejects MoMo callbacks that are not signed', function () {
    Http::fake();
    $payment = Payment::factory()->forOrder(momoOrder())->initiated()->create(['provider' => 'mtn_momo']);

    $this->putJson(route('webhooks.payments', ['provider' => 'mtn_momo', 'reference' => $payment->reference]), ['status' => 'SUCCESSFUL'])
        ->assertUnauthorized();

    expect($payment->fresh()->status)->toBe(PaymentStatus::INITIATED);
    Http::assertNothingSent();
});

it('sends the payer to Flutterwave\'s hosted page', function () {
    Http::fake(['flw.test/v3/payments' => Http::response(['status' => 'success', 'data' => ['link' => 'https://checkout.flutterwave.test/pay/abc']])]);
    $order = momoOrder();

    $this->actingAs($order->buyer)
        ->post(route('orders.pay', $order), ['provider' => 'flutterwave'], ['X-Inertia' => 'true'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'https://checkout.flutterwave.test/pay/abc');

    Http::assertSent(fn (HttpRequest $request) => $request['tx_ref'] === Payment::sole()->reference && $request['currency'] === 'RWF');
});

it('verifies Flutterwave webhooks by hash and by re-checking the transaction', function (string $hash, string $apiStatus, PaymentStatus $expected) {
    Http::fake(['flw.test/v3/transactions/555/verify' => fn () => Http::response(['data' => ['status' => $apiStatus, 'tx_ref' => Payment::sole()->reference]])]);
    $payment = Payment::factory()->forOrder(momoOrder())->initiated()->create(['provider' => 'flutterwave', 'provider_reference' => null]);

    $this->postJson(route('webhooks.payments', ['provider' => 'flutterwave']), ['data' => ['id' => 555, 'tx_ref' => $payment->reference, 'status' => 'successful']], ['verif-hash' => $hash]);

    expect($payment->fresh()->status)->toBe($expected);
})->with([
    'genuine and successful' => ['my-secret-hash', 'successful', PaymentStatus::HELD],
    'genuine but the API says failed' => ['my-secret-hash', 'failed', PaymentStatus::FAILED],
    'forged hash' => ['wrong-hash', 'successful', PaymentStatus::INITIATED],
]);

it('refunds through MoMo disbursements', function () {
    Http::fake([
        'momo.test/disbursement/token/' => Http::response(['access_token' => 'dtoken']),
        'momo.test/disbursement/v1_0/refund' => Http::response(null, 202),
    ]);

    $result = app(PaymentGateways::class)->driver('mtn_momo')->refund('ref-to-refund', 15000, 'RWF', 'our-ref');

    expect($result->accepted)->toBeTrue();
    Http::assertSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/disbursement/v1_0/refund') && $request['referenceIdToRefund'] === 'ref-to-refund');
});
