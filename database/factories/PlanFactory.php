<?php

namespace Database\Factories;

use App\Enums\BillingInterval;
use App\Enums\PlanAudience;
use App\Enums\PlanLimit;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Defaults to a free marketplace plan. Use sellerOf() for a seller plan.
 *
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'audience' => PlanAudience::MARKETPLACE,
            'name' => fake()->unique()->word().' plan',
            'description' => fake()->sentence(),
            'price' => 0,
            'currency' => 'RWF',
            'interval' => BillingInterval::MONTH,
            'trial_days' => 0,
            'limits' => [],
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function sellerOf(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
            'audience' => PlanAudience::SELLER,
            'currency' => $tenant->default_currency,
        ]);
    }

    public function paid(int $price = 25000): static
    {
        return $this->state(fn (array $attributes) => ['price' => $price]);
    }

    public function withTrial(int $days = 14): static
    {
        return $this->state(fn (array $attributes) => ['trial_days' => $days]);
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => ['is_default' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function limit(PlanLimit $limit, int|bool|null $value): static
    {
        return $this->state(fn (array $attributes) => [
            'limits' => [...($attributes['limits'] ?? []), $limit->value => $value],
        ]);
    }
}
