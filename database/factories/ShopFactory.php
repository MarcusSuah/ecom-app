<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A shop in Kigali city centre: RWF 1,000 base fee, RWF 300 per km after the
 * first, free from RWF 20,000, 5 km radius, open 08:00–20:00 every day.
 *
 * @extends Factory<Shop>
 */
class ShopFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory()->shop(),
            'lat' => -1.9441,
            'lng' => 30.0619,
            'address_text' => 'KN 4 Ave, Kigali',
            'landmark' => null,
            'offers_pickup' => true,
            'offers_delivery' => true,
            'base_fee' => 1000,
            'per_km_rate' => 300,
            'free_threshold' => 20000,
            'delivery_radius_km' => 5,
            'opening_hours' => array_fill_keys(Shop::DAYS, ['open' => '08:00', 'close' => '20:00']),
            'currency' => 'RWF',
        ];
    }

    public function at(float $lat, float $lng): static
    {
        return $this->state(fn (array $attributes) => ['lat' => $lat, 'lng' => $lng]);
    }

    public function pickupOnly(): static
    {
        return $this->state(fn (array $attributes) => ['offers_delivery' => false, 'offers_pickup' => true]);
    }
}
