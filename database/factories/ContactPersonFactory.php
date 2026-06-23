<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ContactPerson>
 *
 * `company_id` and `party_id` are supplied by the caller — usually via
 * `->for($party)` with an active tenant context.
 */
class ContactPersonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'designation' => fake()->jobTitle(),
            'phone' => fake()->numerify('98########'),
            'email' => fake()->safeEmail(),
            'is_primary' => false,
        ];
    }
}
