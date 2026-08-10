<?php

namespace Database\Factories;

use App\Models\Membership;
use App\Enums\MembershipStatusEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * `member_id` and `membership_plan_id` are intentionally absent — a term only
 * makes sense against a real member and plan, so tests pass both explicitly.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Membership>
 */
class MembershipFactory extends Factory
{
    protected $model = Membership::class;

    public function definition(): array
    {
        return [
            'membership_no' => 'MSHIP-'.fake()->unique()->numberBetween(10000, 99999),
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonthNoOverflow()->subDay()->toDateString(),
            'status' => MembershipStatusEnum::Active,
            'price' => 2000,
            'discount_amount' => 0,
            'joining_fee' => 0,
            'payable_amount' => 2000,
        ];
    }

    public function status(MembershipStatusEnum $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }

    /** A term whose last day is `$days` from today. */
    public function endingInDays(int $days): static
    {
        return $this->state(fn (): array => [
            'end_date' => now()->addDays($days)->toDateString(),
        ]);
    }

    public function expiredOn(string $date): static
    {
        return $this->state(fn (): array => [
            'start_date' => \Carbon\Carbon::parse($date)->subMonthNoOverflow()->toDateString(),
            'end_date' => $date,
        ]);
    }
}
