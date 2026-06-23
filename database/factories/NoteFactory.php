<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Note>
 *
 * The polymorphic `notable` (e.g. a Party) and `company_id` are supplied by the
 * caller — usually via `->for($party, 'notable')` with an active tenant context.
 */
class NoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'body' => fake()->sentence(),
        ];
    }
}
