<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;

/**
 * A subscriber has one plan at a time: once a new subscription starts, the
 * others end immediately and their unpaid invoices are voided.
 */
final class ReplacePreviousSubscriptions
{
    public static function for(Subscription $current): void
    {
        $previous = Subscription::withoutGlobalScopes()
            ->where('subscriber_type', $current->subscriber_type)
            ->where('subscriber_id', $current->subscriber_id)
            ->whereKeyNot($current->id)
            ->whereIn('status', [...SubscriptionStatus::live(), SubscriptionStatus::PENDING])
            ->pluck('id');

        if ($previous->isEmpty()) {
            return;
        }

        Subscription::withoutGlobalScopes()->whereIn('id', $previous)->update([
            'status' => SubscriptionStatus::EXPIRED,
            'ends_at' => now(),
        ]);

        SubscriptionInvoice::withoutGlobalScopes()
            ->whereIn('subscription_id', $previous)
            ->where('status', InvoiceStatus::PENDING)
            ->update(['status' => InvoiceStatus::VOID]);
    }
}
