<?php

namespace Database\Factories;

use App\Enums\CompanyType;
use App\Enums\VerificationStatus;
use App\Models\Company;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Companies belong to the current tenant (BelongsToTenant), so a tenant
 * must be current when this factory runs.
 *
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'type' => CompanyType::SUPPLIER,
            'name' => fake()->unique()->company(),
            'description' => fake()->paragraph(),
            'country' => 'RW',
            'city' => fake()->randomElement(['Kigali', 'Musanze', 'Huye', 'Rubavu', 'Rwamagana']),
            'phone' => '+2507'.fake()->numerify('########'),
            'email' => fake()->companyEmail(),
        ];
    }

    /**
     * Give the owner the role that matches the company type, as CreateCompany does.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Company $company): void {
            $company->owner->assignRole($company->type->ownerRole()->value);
        });
    }

    public function supplier(): static
    {
        return $this->state(fn (array $attributes) => ['type' => CompanyType::SUPPLIER]);
    }

    public function shop(): static
    {
        return $this->state(fn (array $attributes) => ['type' => CompanyType::SHOP]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => ['is_featured' => true]);
    }

    public function verified(): static
    {
        return $this->withVerification(VerificationStatus::APPROVED);
    }

    public function pendingVerification(): static
    {
        return $this->withVerification(VerificationStatus::PENDING);
    }

    public function rejectedVerification(): static
    {
        return $this->withVerification(VerificationStatus::REJECTED);
    }

    private function withVerification(VerificationStatus $status): static
    {
        return $this->afterCreating(function (Company $company) use ($status): void {
            Verification::factory()->for($company)->status($status)->create();
        });
    }
}
