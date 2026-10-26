<?php

namespace Database\Factories;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company().' Market';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'domain' => null,
            'status' => TenantStatus::ACTIVE,
            'default_currency' => 'RWF',
            'support_email' => fake()->safeEmail(),
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => ['status' => TenantStatus::SUSPENDED]);
    }
}
