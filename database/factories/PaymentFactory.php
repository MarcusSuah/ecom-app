<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A held fake payment. Use forOrder() so amount and tenant match the order.
 *
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'reference' => (string) Str::uuid(),
            'provider' => 'fake',
            'provider_reference' => 'fake_'.Str::lower(Str::ulid()),
            'amount' => 5000,
            'currency' => 'RWF',
            'status' => PaymentStatus::HELD,
            'held_at' => now(),
        ];
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn (array $attributes) => [
            'order_id' => $order->id,
            'tenant_id' => $order->tenant_id,
            'amount' => $order->total,
            'currency' => $order->currency,
        ]);
    }

    public function initiated(): static
    {
        return $this->state(fn (array $attributes) => ['status' => PaymentStatus::INITIATED, 'held_at' => null]);
    }
}
