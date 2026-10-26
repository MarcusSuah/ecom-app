<?php

namespace Database\Factories;

use App\Models\CurrencyRate;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A shared fetched rate of 1 USD = 1,450 RWF.
 *
 * @extends Factory<CurrencyRate>
 */
class CurrencyRateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'base' => 'USD',
            'quote' => 'RWF',
            'rate' => '1450',
            'source' => 'fixed',
            'fetched_at' => now(),
        ];
    }

    public function rate(string $rate): static
    {
        return $this->state(fn (array $attributes) => ['rate' => $rate]);
    }

    /**
     * A marketplace's manual override.
     */
    public function overrideFor(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => ['tenant_id' => $tenant->id, 'source' => CurrencyRate::SOURCE_MANUAL]);
    }
}
