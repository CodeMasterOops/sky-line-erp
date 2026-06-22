<?php

namespace Database\Factories;

use App\Enums\FollowUpStatusEnum;
use App\Enums\FollowUpChannelEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FollowUp>
 *
 * `company_id`, `branch_id` and `party_id` are supplied by the caller — usually
 * via `->for($party)` with an active tenant context.
 */
class FollowUpFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'channel' => fake()->randomElement(FollowUpChannelEnum::values()),
            'scheduled_at' => fake()->dateTimeBetween('now', '+2 weeks'),
            'status' => FollowUpStatusEnum::Pending->value,
            'note' => fake()->optional()->sentence(),
        ];
    }

    public function done(): static
    {
        return $this->state(fn () => [
            'status' => FollowUpStatusEnum::Done->value,
            'completed_at' => now(),
            'outcome' => fake()->sentence(),
        ]);
    }
}
