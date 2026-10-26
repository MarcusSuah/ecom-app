<?php

namespace App\Services\Payments;

use App\Services\Billing\InvalidWebhookSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Completes every payment at once. Webhooks carry an HMAC-SHA256 of the
 * raw body in X-Fake-Signature, so the webhook path can be exercised like
 * a real provider's.
 */
class FakePaymentGateway implements PaymentGateway
{
    public function __construct(private string $webhookSecret) {}

    public function name(): string
    {
        return 'fake';
    }

    public function initiate(ChargeRequest $charge): PaymentInitiation
    {
        return new PaymentInitiation(
            outcome: PaymentOutcome::SUCCEEDED,
            providerReference: 'fake_'.Str::lower(Str::ulid()),
            payload: ['reference' => $charge->reference, 'amount' => $charge->amount, 'currency' => $charge->currency],
        );
    }

    public function verifyWebhook(Request $request): PaymentWebhookEvent
    {
        if (! hash_equals($this->sign($request->getContent()), (string) $request->header('X-Fake-Signature'))) {
            throw new InvalidWebhookSignature('The webhook signature is not valid.');
        }

        return new PaymentWebhookEvent(
            reference: (string) $request->input('reference'),
            outcome: PaymentOutcome::tryFrom((string) $request->input('status')) ?? PaymentOutcome::PENDING,
            providerReference: $request->filled('provider_reference') ? (string) $request->input('provider_reference') : null,
            payload: $request->all(),
        );
    }

    public function refund(string $providerReference, int $amount, string $currency, string $reference): RefundResult
    {
        return new RefundResult(accepted: true, providerReference: 'fake_refund_'.Str::lower(Str::ulid()));
    }

    public function sign(string $body): string
    {
        return hash_hmac('sha256', $body, $this->webhookSecret);
    }
}
