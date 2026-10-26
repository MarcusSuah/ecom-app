<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceStatus;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class IssueInvoice
{
    /**
     * Bill a subscription for one period at its locked-in price.
     */
    public function handle(Subscription $subscription, CarbonInterface $periodStartsAt, CarbonInterface $periodEndsAt): SubscriptionInvoice
    {
        return SubscriptionInvoice::create([
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->id,
            'number' => 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
            'amount' => $subscription->price,
            'currency' => $subscription->currency,
            'status' => InvoiceStatus::PENDING,
            'period_starts_at' => $periodStartsAt,
            'period_ends_at' => $periodEndsAt,
        ]);
    }
}
