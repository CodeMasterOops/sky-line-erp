<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\Company;
use App\Enums\BillingCycleEnum;
use App\Enums\SubscriptionStatusEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        $billingCycle = fake()->randomElement(BillingCycleEnum::cases());
        $plan = Plan::factory()->create();

        return [
            'company_id' => Company::query()->value('id') ?? 1,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatusEnum::Active,
            'billing_cycle' => $billingCycle,
            'price' => $plan->priceForCycle($billingCycle),
            'starts_at' => now(),
            'ends_at' => null,
            'trial_ends_at' => null,
            'cancelled_at' => null,
            'assigned_by' => null,
            'notes' => null,
        ];
    }

    public function trialing(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatusEnum::Trialing,
            'trial_ends_at' => now()->addDays(14),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatusEnum::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
