<?php

namespace Database\Factories;

use App\Enums\CrmLeadStatusEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CrmLeadProfile>
 *
 * `company_id` and `party_id` are supplied by the caller — usually via
 * `->for($party)` with an active tenant context.
 */
class CrmLeadProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => CrmLeadStatusEnum::New->value,
            'source' => fake()->randomElement(['referral', 'walk_in', 'website', 'phone', 'other']),
            'expected_value' => fake()->randomFloat(2, 0, 100000),
        ];
    }
}
