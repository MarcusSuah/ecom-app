<?php

namespace Database\Factories;

use App\Enums\ProductUnit;
use App\Enums\RfqStatus;
use App\Models\Category;
use App\Models\Company;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An open request for 1,000 pieces. Use sentTo() to add recipients.
 *
 * @extends Factory<Rfq>
 */
class RfqFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'buyer_id' => User::factory(),
            'category_id' => Category::factory(),
            'title' => rtrim(fake()->sentence(3), '.'),
            'quantity' => 1000,
            'unit' => ProductUnit::PIECE,
            'deliver_city' => 'Kigali',
            'needed_by' => now()->addMonth(),
            'notes' => null,
            'status' => RfqStatus::OPEN,
            'expires_at' => now()->addDays(Rfq::DEFAULT_LIFETIME_DAYS),
        ];
    }

    public function status(RfqStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => ['expires_at' => now()->subMinute()]);
    }

    /**
     * Record these suppliers as recipients, notified now.
     */
    public function sentTo(Company ...$companies): static
    {
        return $this->afterCreating(function (Rfq $rfq) use ($companies): void {
            foreach ($companies as $company) {
                $rfq->recipients()->create(['company_id' => $company->id, 'notified_at' => now()]);
            }
        });
    }
}
