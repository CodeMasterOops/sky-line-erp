<?php

namespace Database\Factories;

use App\Enums\LeadStatusEnum;
use App\Enums\BusinessTypeEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Lead>
 */
class LeadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_name' => fake()->company(),
            'pan' => fake()->numerify('#########'),
            'registration_number' => fake()->numerify('REG-#####'),
            'business_type' => fake()->randomElement(BusinessTypeEnum::cases())->value,
            'full_name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->numerify('98########'),
            'plan_interest' => fake()->randomElement(['basic', 'standard', 'premium', null]),
            'branch_count' => fake()->numberBetween(1, 20),
            'note' => fake()->optional()->sentence(),
            'status' => LeadStatusEnum::New->value,
            'follow_up_note' => null,
            'followed_up_at' => null,
            'ip_address' => fake()->ipv4(),
            'source' => 'website',
        ];
    }

    public function contacted(): static
    {
        return $this->state(['status' => LeadStatusEnum::Contacted->value]);
    }

    public function demoGiven(): static
    {
        return $this->state(['status' => LeadStatusEnum::DemoGiven->value]);
    }

    public function converted(): static
    {
        return $this->state(['status' => LeadStatusEnum::Converted->value]);
    }

    public function lost(): static
    {
        return $this->state(['status' => LeadStatusEnum::Lost->value]);
    }
}
