<?php

namespace Database\Factories;

use App\Models\Member;
use App\Enums\GenderEnum;
use App\Enums\MemberStatusEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * `party_id` is intentionally absent — a member is always created alongside its
 * party (see MemberService::create). Tests that build one by hand pass the
 * party explicitly.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Member>
 */
class MemberFactory extends Factory
{
    protected $model = Member::class;

    public function definition(): array
    {
        return [
            'member_code' => 'MEM-'.fake()->unique()->numberBetween(10000, 99999),
            'gender' => fake()->randomElement(GenderEnum::cases()),
            'joined_on' => now()->toDateString(),
            'status' => MemberStatusEnum::Inactive,
        ];
    }

    public function status(MemberStatusEnum $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
