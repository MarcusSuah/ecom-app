<?php

namespace Database\Factories;

use App\Enums\BillingInterval;
use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Use forSubscriber() to attach it; price and currency follow the plan.
 *
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'status' => SubscriptionStatus::ACTIVE,
            'price' => 0,
            'currency' => 'RWF',
            'interval' => BillingInterval::MONTH,
            'current_period_starts_at' => now(),
            'current_period_ends_at' => now()->addMonth(),
        ];
    }

    public function forSubscriber(Tenant|Company $subscriber, Plan $plan): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $subscriber->billingTenantId(),
            'subscriber_type' => $subscriber->getMorphClass(),
            'subscriber_id' => $subscriber->id,
            'plan_id' => $plan->id,
            'price' => $plan->price,
            'currency' => $plan->currency,
            'interval' => $plan->interval,
        ]);
    }

    public function status(SubscriptionStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }

    public function periodEnded(int $daysAgo = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'current_period_starts_at' => now()->subMonth()->subDays($daysAgo),
            'current_period_ends_at' => now()->subDays($daysAgo),
        ]);
    }
}
