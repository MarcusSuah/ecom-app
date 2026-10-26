<?php

namespace App\Models\Concerns;

use App\Enums\PlanLimit;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Something that subscribes to a plan: a tenant (to a marketplace plan) or
 * a company (to one of its marketplace's seller plans).
 */
trait HasSubscriptions
{
    /**
     * The tenant whose records the subscription and its invoices belong to.
     */
    abstract public function billingTenantId(): int;

    /**
     * @return MorphMany<Subscription, $this>
     */
    public function subscriptions(): MorphMany
    {
        return $this->morphMany(Subscription::class, 'subscriber');
    }

    /**
     * The newest subscription that currently grants access to its plan.
     */
    public function currentSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->whereIn('status', SubscriptionStatus::live())
            ->where(fn (Builder $query) => $query
                ->where('status', '!=', SubscriptionStatus::CANCELLED)
                ->orWhere('ends_at', '>', now()))
            ->with('plan')
            ->latest('id')
            ->first();
    }

    /**
     * The plan used when there is no current subscription.
     */
    public function fallbackPlan(): ?Plan
    {
        return null;
    }

    /**
     * The plan whose limits apply right now.
     */
    public function effectivePlan(): ?Plan
    {
        return $this->currentSubscription()->plan ?? $this->fallbackPlan();
    }

    /**
     * What the effective plan allows for this limit. With no plan at all,
     * switches are off and numeric limits are zero.
     */
    public function planLimit(PlanLimit $limit): int|bool|null
    {
        $plan = $this->effectivePlan();

        if (! $plan) {
            return $limit->isSwitch() ? false : 0;
        }

        return $plan->limit($limit);
    }

    /**
     * Whether one more of something fits within a numeric limit.
     */
    public function withinPlanLimit(PlanLimit $limit, int $currentUsage): bool
    {
        $allowed = $this->planLimit($limit);

        return $allowed === null || $currentUsage < (int) $allowed;
    }
}
