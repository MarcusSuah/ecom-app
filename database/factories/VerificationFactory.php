<?php

namespace Database\Factories;

use App\Enums\VerificationStatus;
use App\Models\Company;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Verification>
 */
class VerificationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'status' => VerificationStatus::PENDING,
            'registration_number' => 'RDB-'.fake()->numerify('#########'),
            'documents' => [[
                'path' => 'verifications/sample/certificate.pdf',
                'name' => 'certificate-of-incorporation.pdf',
                'mime' => 'application/pdf',
                'size' => 120_000,
            ]],
            'notes' => null,
        ];
    }

    public function status(VerificationStatus $status): static
    {
        return match ($status) {
            VerificationStatus::PENDING => $this->state(fn (array $attributes) => ['status' => VerificationStatus::PENDING]),
            VerificationStatus::APPROVED => $this->approved(),
            VerificationStatus::REJECTED => $this->rejected(),
        };
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VerificationStatus::APPROVED,
            'reviewer_id' => User::factory(),
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VerificationStatus::REJECTED,
            'reviewer_id' => User::factory(),
            'reviewed_at' => now(),
            'rejection_reason' => 'The registration certificate is not readable.',
        ]);
    }
}
