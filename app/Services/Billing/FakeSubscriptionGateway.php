<?php

namespace App\Services\Billing;

use App\Models\SubscriptionInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Completes every checkout immediately. Webhooks are signed with an
 * HMAC-SHA256 of the raw body in the X-Fake-Signature header, so the
 * webhook path can be exercised the same way a real provider would.
 */
class FakeSubscriptionGateway implements SubscriptionGateway
{
    public function __construct(private string $webhookSecret) {}

    public function name(): string
    {
        return 'fake';
    }

    public function checkout(SubscriptionInvoice $invoice, string $returnUrl): CheckoutResult
    {
        return new CheckoutResult(
            reference: 'fake_'.Str::lower(Str::ulid()),
            paid: true,
            payload: ['invoice' => $invoice->number, 'amount' => $invoice->amount, 'currency' => $invoice->currency],
        );
    }

    public function parseWebhook(Request $request): PaymentNotification
    {
        $expected = $this->sign($request->getContent());

        if (! hash_equals($expected, (string) $request->header('X-Fake-Signature'))) {
            throw new InvalidWebhookSignature('The webhook signature is not valid.');
        }

        return new PaymentNotification(
            invoiceNumber: (string) $request->input('invoice'),
            reference: (string) $request->input('reference'),
            paid: $request->input('status') === 'paid',
            payload: $request->all(),
        );
    }

    public function sign(string $body): string
    {
        return hash_hmac('sha256', $body, $this->webhookSecret);
    }
}
