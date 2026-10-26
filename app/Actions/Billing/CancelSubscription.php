<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Validation\ValidationException;

class CancelSubscription
{
    /**
     * Stop renewing. The plan stays usable until the end of the period (or
     * trial) that has already been paid for, then the subscription expires.
     */
    public function handle(Subscription $subscription): Subscription
    {
        if (! $subscription->isCancellable()) {
            throw ValidationException::withMessages(['subscription' => 'This subscription cannot be cancelled.']);
        }

        $endsAt = $subscription->status === SubscriptionStatus::PAST_DUE
            ? now()
            : ($subscription->current_period_ends_at ?? now());

        $subscription->update([
            'status' => SubscriptionStatus::CANCELLED,
            'cancelled_at' => now(),
            'ends_at' => $endsAt,
        ]);

        $subscription->invoices()->where('status', InvoiceStatus::PENDING)->update(['status' => InvoiceStatus::VOID]);

        return $subscription;
    }
}
