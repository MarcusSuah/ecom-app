<?php

namespace Database\Factories;

use App\Enums\Fulfilment;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Company;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A paid local pickup order of RWF 5,000.
 *
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'ORD-'.Str::upper(Str::random(10)),
            'buyer_id' => User::factory(),
            'seller_company_id' => Company::factory()->shop(),
            'type' => OrderType::LOCAL,
            'fulfilment' => Fulfilment::PICKUP,
            'address_snapshot' => null,
            'subtotal' => 5000,
            'delivery_fee' => 0,
            'total' => 5000,
            'currency' => 'RWF',
            'status' => OrderStatus::PAID,
            'placed_at' => now(),
        ];
    }

    public function status(OrderStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }

    public function delivery(): static
    {
        return $this->state(fn (array $attributes) => ['fulfilment' => Fulfilment::DELIVERY]);
    }
}
