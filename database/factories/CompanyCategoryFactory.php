<?php

namespace Database\Factories;

use Illuminate\Support\Str;
use App\Models\CompanyCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CompanyCategory>
 */
class CompanyCategoryFactory extends Factory
{
    protected $model = CompanyCategory::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'icon' => 'ti ti-building',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => fake()->numberBetween(1, 100),
        ];
    }

    /**
     * Marks the category as the catalogue default. The slug is left to the
     * factory's unique generator — the seeded catalogue already owns 'general',
     * and what matters here is the flag, not the name.
     */
    public function default(): static
    {
        return $this->state(fn (): array => ['is_default' => true]);
    }

    /**
     * Attach the given module keys as this category's defaults.
     *
     * @param  list<string>  $moduleKeys
     */
    public function withModules(array $moduleKeys): static
    {
        return $this->afterCreating(function (CompanyCategory $category) use ($moduleKeys): void {
            foreach ($moduleKeys as $index => $moduleKey) {
                $category->modules()->create([
                    'module_key' => $moduleKey,
                    'is_default_enabled' => true,
                    'sort_order' => $index,
                ]);
            }
        });
    }
}
