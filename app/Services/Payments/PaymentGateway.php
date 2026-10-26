<?php

namespace App\Services\Payments;

use App\Services\Billing\InvalidWebhookSignature;
use Illuminate\Http\Request;

/**
 * Collects order payments. Drivers: "fake" (local, tests), "mtn_momo" and
 * "flutterwave" (built against their sandbox APIs; credentials from env).
 */
interface PaymentGateway
{
    public function name(): string;

    /**
     * Ask the provider to collect a payment. It may complete at once
     * (fake), wait for the payer to approve on their phone (MoMo) or send
     * the payer to a hosted page (Flutterwave).
     */
    public function initiate(ChargeRequest $charge): PaymentInitiation;

    /**
     * Check a webhook is genuine and read the payment result from it,
     * confirming with the provider where the callback itself is not signed.
     *
     * @throws InvalidWebhookSignature
     */
    public function verifyWebhook(Request $request): PaymentWebhookEvent;

    /**
     * Return money to the payer.
     */
    public function refund(string $providerReference, int $amount, string $currency, string $reference): RefundResult;
}
