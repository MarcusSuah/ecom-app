<?php

namespace Database\Factories;

use App\Enums\QuoteStatus;
use App\Models\Company;
use App\Models\Quote;
use App\Models\Rfq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A submitted RWF quote with tiers 100 @ 1,000 and 1,000 @ 900.
 *
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rfq_id' => Rfq::factory(),
            'company_id' => Company::factory()->supplier()->verified(),
            'currency' => 'RWF',
            'lead_time_min_days' => 7,
            'lead_time_max_days' => 14,
            'sample_terms' => 'Free samples, buyer pays shipping.',
            'valid_until' => now()->addMonth(),
            'message' => null,
            'status' => QuoteStatus::SUBMITTED,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Quote $quote): void {
            if ($quote->tiers()->doesntExist()) {
                $quote->tiers()->createMany([
                    ['min_qty' => 100, 'unit_price' => 1000],
                    ['min_qty' => 1000, 'unit_price' => 900],
                ]);
            }
        });
    }

    /**
     * @param  non-empty-list<array{0: int, 1: int}>  $tiers  [min_qty, unit_price] pairs
     */
    public function tiers(array $tiers): static
    {
        return $this->afterCreating(function (Quote $quote) use ($tiers): void {
            $quote->tiers()->delete();
            $quote->tiers()->createMany(array_map(fn (array $tier) => ['min_qty' => $tier[0], 'unit_price' => $tier[1]], $tiers));
        });
    }

    public function status(QuoteStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }
}
