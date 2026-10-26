<?php

namespace App\Actions\Billing;

use App\Enums\PlanAudience;
use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ActivatePlanManually
{
    public function __construct(
        private IssueInvoice $issueInvoice,
        private RecordInvoicePayment $recordInvoicePayment,
    ) {}

    /**
     * Put a subscriber on a plan for a number of periods paid outside the
     * gateway (bank transfer, cash, invoice). Recorded as a "manual" payment.
     */
    public function handle(Tenant|Company $subscriber, Plan $plan, int $periods, User $recordedBy, ?string $reference = null): Subscription
    {
        $matchesAudience = $subscriber instanceof Tenant
            ? $plan->audience === PlanAudience::MARKETPLACE
            : $plan->audience === PlanAudience::SELLER && $plan->tenant_id === $subscriber->tenant_id;

        if (! $matchesAudience) {
            throw ValidationException::withMessages(['plan_id' => 'This plan cannot be used for this subscriber.']);
        }

        if ($reference && SubscriptionInvoice::withoutGlobalScopes()->where('provider', 'manual')->where('provider_reference', $reference)->exists()) {
            throw ValidationException::withMessages(['reference' => 'A payment with this reference has already been recorded.']);
        }

        return DB::transaction(function () use ($subscriber, $plan, $periods, $recordedBy, $reference): Subscription {
            $subscription = $subscriber->subscriptions()->create([
                'tenant_id' => $subscriber->billingTenantId(),
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::PENDING,
                'price' => $plan->price,
                'currency' => $plan->currency,
                'interval' => $plan->interval,
            ]);

            $startsAt = now();
            $endsAt = $startsAt;
            for ($i = 0; $i < $periods; $i++) {
                $endsAt = $plan->interval->addTo($endsAt);
            }

            $invoice = $this->issueInvoice->handle($subscription, $startsAt, $endsAt);
            $invoice->update(['amount' => $plan->price * $periods]);

            $this->recordInvoicePayment->handle(
                $invoice,
                'manual',
                $reference ?: 'manual_'.Str::lower(Str::ulid()),
                ['recorded_by' => $recordedBy->id, 'periods' => $periods],
            );

            return $subscription->refresh();
        });
    }
}
