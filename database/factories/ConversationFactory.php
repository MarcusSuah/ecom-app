<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Use between() to add the member and the company owner as participants.
 *
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'subject' => rtrim(fake()->sentence(3), '.'),
        ];
    }

    public function between(User $member, Company $company): static
    {
        return $this->state(fn (array $attributes) => ['company_id' => $company->id])
            ->afterCreating(fn (Conversation $conversation) => $conversation->participants()->attach([$member->id, $company->owner_id]));
    }
}
