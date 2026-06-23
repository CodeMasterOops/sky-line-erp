<?php

namespace Database\Factories;

use App\Enums\TaskStatusEnum;
use App\Enums\TaskPriorityEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
 *
 * `company_id` and `branch_id` are supplied by the caller within an active
 * tenant context; attach a `taskable` via `->for($party, 'taskable')` when the
 * task relates to a party.
 */
class TaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'priority' => fake()->randomElement(TaskPriorityEnum::values()),
            'status' => TaskStatusEnum::Open->value,
            'due_date' => fake()->dateTimeBetween('now', '+1 month')->format('Y-m-d'),
        ];
    }

    public function done(): static
    {
        return $this->state(fn () => [
            'status' => TaskStatusEnum::Done->value,
            'completed_at' => now(),
        ]);
    }
}
