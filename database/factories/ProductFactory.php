<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Enums\ProductUnit;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Products belong to the current tenant, so a tenant must be current. By
 * default a draft with tiers 100 @ 1,000 / 500 @ 900 / 1,000 @ 800 (RWF).
 *
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory()->supplier(),
            'category_id' => Category::factory(),
            'name' => rtrim(fake()->unique()->sentence(3), '.'),
            'description' => fake()->paragraph(),
            'unit' => ProductUnit::PIECE,
            'moq' => 100,
            'lead_time_min_days' => 7,
            'lead_time_max_days' => 14,
            'currency' => 'RWF',
            'origin_country' => 'RW',
            'sample_available' => false,
            'status' => ProductStatus::DRAFT,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Product $product): void {
            if ($product->priceTiers()->exists()) {
                return;
            }

            $this->createTiers($product, [[$product->moq, 1000], [$product->moq * 5, 900], [$product->moq * 10, 800]]);
        });
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ProductStatus::ACTIVE]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ProductStatus::ARCHIVED]);
    }

    /**
     * Replace the default tiers.
     *
     * @param  non-empty-list<array{0: int, 1: int}>  $tiers  [min_qty, unit_price] pairs
     */
    public function tiers(array $tiers): static
    {
        return $this->afterCreating(function (Product $product) use ($tiers): void {
            $product->priceTiers()->delete();
            $this->createTiers($product, $tiers);
        });
    }

    /**
     * @param  non-empty-list<array{0: int, 1: int}>  $tiers
     */
    private function createTiers(Product $product, array $tiers): void
    {
        $product->priceTiers()->createMany(array_map(fn (array $tier) => ['min_qty' => $tier[0], 'unit_price' => $tier[1]], $tiers));
        $product->forceFill(['min_unit_price' => min(array_column($tiers, 1))])->saveQuietly();
        $product->unsetRelation('priceTiers');
    }
}
