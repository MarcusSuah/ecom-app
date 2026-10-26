<?php

namespace App\Services\Billing;

use App\Models\SubscriptionInvoice;
use Illuminate\Http\Request;

/**
 * Collects subscription payments. Implementations: "fake" for development
 * and tests; MTN MoMo and Flutterwave arrive with the Phase 5 payments work.
 */
interface SubscriptionGateway
{
    /**
     * The provider name stored on invoices (e.g. "fake", "mtn_momo").
     */
    public function name(): string;

    /**
     * Start collecting payment for an invoice. Either the payment completes
     * at once, or the payer is sent to $returnUrl after paying elsewhere and
     * the result arrives later through the webhook.
     */
    public function checkout(SubscriptionInvoice $invoice, string $returnUrl): CheckoutResult;

    /**
     * Verify a webhook's signature and read the payment result from it.
     *
     * @throws InvalidWebhookSignature
     */
    public function parseWebhook(Request $request): PaymentNotification;
}
