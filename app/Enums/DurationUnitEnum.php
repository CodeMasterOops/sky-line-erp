<?php

namespace App\Enums;

use Carbon\CarbonInterface;

/**
 * The unit a membership term is measured in. Kept separate from the presets so
 * a gym can price an arbitrary term (10 days, 8 weeks) without needing a new
 * enum case.
 */
enum DurationUnitEnum: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';

    public function label(): string
    {
        return match ($this) {
            self::Day => 'Day',
            self::Week => 'Week',
            self::Month => 'Month',
            self::Year => 'Year',
        };
    }

    /**
     * Add this many units to a date.
     *
     * Month and year arithmetic uses the no-overflow variants deliberately:
     * 31 January + 1 month must land on 28/29 February, not spill into March.
     */
    public function addTo(CarbonInterface $date, int $value): CarbonInterface
    {
        return match ($this) {
            self::Day => $date->copy()->addDays($value),
            self::Week => $date->copy()->addWeeks($value),
            self::Month => $date->copy()->addMonthsNoOverflow($value),
            self::Year => $date->copy()->addYearsNoOverflow($value),
        };
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => ['id' => $case->value, 'name' => $case->label()],
            self::cases(),
        );
    }
}
