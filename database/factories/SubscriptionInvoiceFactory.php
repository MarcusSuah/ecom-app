<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SubscriptionInvoice>
 */
class SubscriptionInvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
            'amount' => 25000,
            'currency' => 'RWF',
            'status' => InvoiceStatus::PENDING,
            'period_starts_at' => now(),
            'period_ends_at' => now()->addMonth(),
        ];
    }

    public function forSubscription(Subscription $subscription): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->id,
            'amount' => $subscription->price,
            'currency' => $subscription->currency,
        ]);
    }
}
