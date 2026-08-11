<?php

namespace Database\Factories;

use App\Models\MembershipPlan;
use App\Enums\DurationUnitEnum;
use App\Enums\MembershipDurationPresetEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MembershipPlan>
 */
class MembershipPlanFactory extends Factory
{
    protected $model = MembershipPlan::class;

    public function definition(): array
    {
        return [
            'code' => 'MPLAN-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Monthly',
            'duration_unit' => DurationUnitEnum::Month,
            'duration_value' => 1,
            'preset' => MembershipDurationPresetEnum::Monthly,
            'price' => 2000,
            'joining_fee' => 0,
            'grace_days' => 0,
            'max_freeze_days' => 0,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function preset(MembershipDurationPresetEnum $preset): static
    {
        $duration = $preset->duration();

        return $this->state(fn (): array => [
            'name' => $preset->label(),
            'preset' => $preset,
            'duration_unit' => $duration['unit'] ?? DurationUnitEnum::Month,
            'duration_value' => $duration['value'] ?? 1,
        ]);
    }

    public function custom(DurationUnitEnum $unit, int $value): static
    {
        return $this->state(fn (): array => [
            'preset' => MembershipDurationPresetEnum::forDuration($unit, $value),
            'duration_unit' => $unit,
            'duration_value' => $value,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
