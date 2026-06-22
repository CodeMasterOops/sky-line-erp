<?php

namespace Database\Factories;

use App\Enums\CrmActivityTypeEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CrmActivity>
 *
 * The polymorphic `subject` (e.g. a Party) and `company_id` are supplied by the
 * caller — usually via `->for($party, 'subject')` with an active tenant context.
 */
class CrmActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(CrmActivityTypeEnum::values()),
            'description' => fake()->sentence(),
            'properties' => null,
            'occurred_at' => now(),
        ];
    }
}
