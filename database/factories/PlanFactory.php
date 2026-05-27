<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'price_monthly' => fake()->randomFloat(2, 0, 500),
            'price_yearly' => fake()->randomFloat(2, 0, 5000),
            'features' => [
                fake()->sentence(3),
                fake()->sentence(3),
            ],
            'is_active' => true,
            'is_default' => false,
            'is_recommended' => false,
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => [
            'name' => 'Basic',
            'slug' => 'basic',
            'description' => 'Starter — ideal for startups & shops.',
            'price_monthly' => 999,
            'price_yearly' => 9999,
            'features' => ['1 branch location', 'Purchase & sales management'],
            'is_default' => true,
            'sort_order' => 1,
        ]);
    }
}
