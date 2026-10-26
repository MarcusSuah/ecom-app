<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceStatus;
use App\Models\SubscriptionInvoice;
use App\Services\Billing\CheckoutResult;
use App\Services\Billing\SubscriptionGateway;
use Illuminate\Validation\ValidationException;

class PayInvoice
{
    public function __construct(
        private SubscriptionGateway $gateway,
        private RecordInvoicePayment $recordInvoicePayment,
    ) {}

    /**
     * Send a pending invoice to the payment gateway. When the gateway
     * completes the payment at once, the subscription is activated now;
     * otherwise the webhook activates it later.
     */
    public function handle(SubscriptionInvoice $invoice, string $returnUrl): CheckoutResult
    {
        if ($invoice->status !== InvoiceStatus::PENDING) {
            throw ValidationException::withMessages(['invoice' => 'Only unpaid invoices can be paid.']);
        }

        $checkout = $this->gateway->checkout($invoice, $returnUrl);

        $invoice->update([
            'provider' => $this->gateway->name(),
            'provider_reference' => $checkout->reference,
        ]);

        if ($checkout->paid) {
            $this->recordInvoicePayment->handle($invoice, $this->gateway->name(), $checkout->reference, $checkout->payload);
        }

        return $checkout;
    }
}
