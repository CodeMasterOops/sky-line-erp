<?php

namespace Database\Factories;

use App\Models\MemberCheckIn;
use App\Enums\CheckInMethodEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MemberCheckIn>
 */
class MemberCheckInFactory extends Factory
{
    protected $model = MemberCheckIn::class;

    public function definition(): array
    {
        return [
            'checked_in_at' => now(),
            'method' => CheckInMethodEnum::Manual,
        ];
    }

    public function at(string $timestamp): static
    {
        return $this->state(fn (): array => ['checked_in_at' => $timestamp]);
    }

    public function closed(int $minutes = 60): static
    {
        return $this->state(fn (array $attributes): array => [
            'checked_out_at' => \Carbon\Carbon::parse($attributes['checked_in_at'])->addMinutes($minutes),
        ]);
    }
}
