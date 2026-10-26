<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use Illuminate\Support\Facades\DB;

class RecordInvoicePayment
{
    /**
     * Mark an invoice paid and activate its subscription for the billed
     * period. Safe to call more than once for the same payment: an invoice
     * that is already paid is left as it is.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(SubscriptionInvoice $invoice, string $provider, string $reference, array $payload = []): SubscriptionInvoice
    {
        return DB::transaction(function () use ($invoice, $provider, $reference, $payload): SubscriptionInvoice {
            $invoice = SubscriptionInvoice::withoutGlobalScopes()->lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->isPaid()) {
                return $invoice;
            }

            $invoice->update([
                'status' => InvoiceStatus::PAID,
                'provider' => $provider,
                'provider_reference' => $reference,
                'paid_at' => now(),
                'raw_payload' => $payload ?: null,
            ]);

            $subscription = Subscription::withoutGlobalScopes()->findOrFail($invoice->subscription_id);
            $subscription->update([
                'status' => SubscriptionStatus::ACTIVE,
                'current_period_starts_at' => $invoice->period_starts_at,
                'current_period_ends_at' => $invoice->period_ends_at,
                'cancelled_at' => null,
                'ends_at' => null,
            ]);

            ReplacePreviousSubscriptions::for($subscription);

            return $invoice;
        });
    }
}
