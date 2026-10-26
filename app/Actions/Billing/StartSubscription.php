<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\PlanAudience;
use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Services\Billing\CheckoutResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartSubscription
{
    public function __construct(
        private IssueInvoice $issueInvoice,
        private PayInvoice $payInvoice,
    ) {}

    /**
     * Move a tenant or company onto a plan.
     *
     *  - Free plans start at once.
     *  - Paid plans with trial days start a trial, once per subscriber.
     *  - Other paid plans wait for the first payment; the current plan stays
     *    in force until that payment is recorded.
     *
     * @return array{subscription: Subscription, checkout: CheckoutResult|null}
     */
    public function handle(Tenant|Company $subscriber, Plan $plan, string $returnUrl): array
    {
        $this->ensurePlanIsAvailableTo($subscriber, $plan);

        /** @var array{0: Subscription, 1: SubscriptionInvoice|null} $started */
        $started = DB::transaction(function () use ($subscriber, $plan): array {
            $this->abandonUnpaidAttempts($subscriber);

            $now = now();
            $attributes = [
                'tenant_id' => $subscriber->billingTenantId(),
                'plan_id' => $plan->id,
                'price' => $plan->price,
                'currency' => $plan->currency,
                'interval' => $plan->interval,
            ];

            if ($plan->isFree()) {
                $subscription = $subscriber->subscriptions()->create([
                    ...$attributes,
                    'status' => SubscriptionStatus::ACTIVE,
                    'current_period_starts_at' => $now,
                    'current_period_ends_at' => $plan->interval->addTo($now),
                ]);
                ReplacePreviousSubscriptions::for($subscription);

                return [$subscription, null];
            }

            if ($plan->trial_days > 0 && ! $subscriber->subscriptions()->whereNotNull('trial_ends_at')->exists()) {
                $trialEndsAt = $now->addDays($plan->trial_days);
                $subscription = $subscriber->subscriptions()->create([
                    ...$attributes,
                    'status' => SubscriptionStatus::TRIALING,
                    'trial_ends_at' => $trialEndsAt,
                    'current_period_starts_at' => $now,
                    'current_period_ends_at' => $trialEndsAt,
                ]);
                ReplacePreviousSubscriptions::for($subscription);

                return [$subscription, null];
            }

            $subscription = $subscriber->subscriptions()->create([
                ...$attributes,
                'status' => SubscriptionStatus::PENDING,
            ]);

            return [$subscription, $this->issueInvoice->handle($subscription, $now, $plan->interval->addTo($now))];
        });

        [$subscription, $invoice] = $started;
        $checkout = $invoice ? $this->payInvoice->handle($invoice, $returnUrl) : null;

        return ['subscription' => $subscription->refresh(), 'checkout' => $checkout];
    }

    private function ensurePlanIsAvailableTo(Tenant|Company $subscriber, Plan $plan): void
    {
        $available = $plan->is_active && ($subscriber instanceof Tenant
            ? $plan->audience === PlanAudience::MARKETPLACE && $plan->tenant_id === null
            : $plan->audience === PlanAudience::SELLER && $plan->tenant_id === $subscriber->tenant_id);

        if (! $available) {
            throw ValidationException::withMessages(['plan' => 'This plan is not available.']);
        }

        $current = $subscriber->currentSubscription();

        if ($current && $current->plan_id === $plan->id && $current->status !== SubscriptionStatus::CANCELLED) {
            throw ValidationException::withMessages(['plan' => 'You are already on this plan.']);
        }
    }

    /**
     * Earlier checkouts that were never paid are replaced by this one.
     */
    private function abandonUnpaidAttempts(Tenant|Company $subscriber): void
    {
        $subscriber->subscriptions()
            ->where('status', SubscriptionStatus::PENDING)
            ->each(function (Subscription $pending): void {
                $pending->invoices()->where('status', InvoiceStatus::PENDING)->update(['status' => InvoiceStatus::VOID]);
                $pending->update(['status' => SubscriptionStatus::EXPIRED, 'ends_at' => now()]);
            });
    }
}
