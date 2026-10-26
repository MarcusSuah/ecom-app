<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => 'Home',
            'lat' => -1.9579,
            'lng' => 30.1063,
            'description' => 'Remera, near the stadium',
            'landmark' => null,
            'is_default' => false,
        ];
    }
}
