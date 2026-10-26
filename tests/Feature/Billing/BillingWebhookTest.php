<?php

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;

function pendingInvoice(): SubscriptionInvoice
{
    $plan = Plan::factory()->sellerOf(test()->tenant)->paid()->create();
    $subscription = Subscription::factory()->forSubscriber(Company::factory()->create(), $plan)->status(SubscriptionStatus::PENDING)->create();

    return SubscriptionInvoice::factory()->forSubscription($subscription)->create();
}

function postSignedWebhook(array $payload, ?string $signature = null)
{
    $body = json_encode($payload);
    $signature ??= hash_hmac('sha256', $body, config('billing.gateways.fake.webhook_secret'));

    return test()->call('POST', route('webhooks.billing'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_FAKE_SIGNATURE' => $signature,
    ], $body);
}

it('rejects webhooks with a bad signature', function () {
    $invoice = pendingInvoice();

    postSignedWebhook(['invoice' => $invoice->number, 'reference' => 'ref-1', 'status' => 'paid'], 'forged')
        ->assertUnauthorized();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::PENDING);
});

it('activates the subscription once however often the payment is delivered', function () {
    $invoice = pendingInvoice();
    $payload = ['invoice' => $invoice->number, 'reference' => 'ref-1', 'status' => 'paid'];

    postSignedWebhook($payload)->assertOk();
    $paidAt = $invoice->fresh()->paid_at;

    $this->travel(5)->minutes();
    postSignedWebhook($payload)->assertOk();
    postSignedWebhook([...$payload, 'status' => 'failed'])->assertOk();

    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::PAID)
        ->and($invoice->paid_at->equalTo($paidAt))->toBeTrue()
        ->and($invoice->subscription->status)->toBe(SubscriptionStatus::ACTIVE);
});

it('marks the invoice failed when the payment fails', function () {
    $invoice = pendingInvoice();

    postSignedWebhook(['invoice' => $invoice->number, 'reference' => 'ref-2', 'status' => 'failed'])->assertOk();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::FAILED)
        ->and($invoice->subscription->fresh()->status)->toBe(SubscriptionStatus::PENDING);
});
