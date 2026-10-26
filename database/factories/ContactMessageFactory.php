<?php

namespace Database\Factories;

use App\Enums\ContactTopic;
use App\Models\ContactMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An unhandled message from a guest.
 *
 * @extends Factory<ContactMessage>
 */
class ContactMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => null,
            'topic' => fake()->randomElement(ContactTopic::cases()),
            'message' => fake()->paragraph(),
        ];
    }

    public function handled(): static
    {
        return $this->state(fn (array $attributes) => ['handled_at' => now()]);
    }
}
