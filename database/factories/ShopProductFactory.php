<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Shop;
use App\Models\ShopProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopProduct>
 */
class ShopProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'category_id' => Category::factory(),
            'name' => rtrim(fake()->sentence(2), '.'),
            'description' => fake()->sentence(),
            'price' => 2500,
            'stock' => 50,
            'is_active' => true,
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
