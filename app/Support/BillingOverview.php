<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Plan;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use Illuminate\Support\Collection;

/**
 * The props a billing page needs, for either kind of subscriber.
 */
final class BillingOverview
{
    /**
     * @param  Collection<int, Plan>  $plans
     * @return array<string, mixed>
     */
    public static function for(Tenant|Company $subscriber, Collection $plans): array
    {
        $current = $subscriber->currentSubscription();
        $effectivePlan = $current->plan ?? $subscriber->fallbackPlan();

        $invoices = SubscriptionInvoice::query()
            ->whereIn('subscription_id', $subscriber->subscriptions()->select('id'))
            ->latest('id')
            ->limit(24)
            ->get();

        return [
            'subscription' => $current?->toSummary(),
            'effectivePlan' => $effectivePlan?->toSummary(),
            'plans' => $plans->map(fn (Plan $plan) => $plan->toSummary())->values(),
            'invoices' => $invoices->map(fn (SubscriptionInvoice $invoice) => $invoice->toSummary())->values(),
        ];
    }
}
