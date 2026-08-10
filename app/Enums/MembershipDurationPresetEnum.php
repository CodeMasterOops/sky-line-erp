<?php

namespace App\Enums;

/**
 * The four standard membership terms a gym sells, plus an escape hatch.
 *
 * The preset is a UI convenience: the authoritative term is always
 * `duration_unit` + `duration_value` on the plan, so an arbitrary length stays
 * possible without touching this enum.
 */
enum MembershipDurationPresetEnum: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case HalfYearly = 'half_yearly';
    case Yearly = 'yearly';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly',
            self::Quarterly => 'Quarterly',
            self::HalfYearly => 'Half-Yearly',
            self::Yearly => 'Yearly',
            self::Custom => 'Custom',
        };
    }

    /**
     * @return array{unit: DurationUnitEnum, value: int}|null null for Custom,
     *                                                        which carries its own term.
     */
    public function duration(): ?array
    {
        return match ($this) {
            self::Monthly => ['unit' => DurationUnitEnum::Month, 'value' => 1],
            self::Quarterly => ['unit' => DurationUnitEnum::Month, 'value' => 3],
            self::HalfYearly => ['unit' => DurationUnitEnum::Month, 'value' => 6],
            self::Yearly => ['unit' => DurationUnitEnum::Year, 'value' => 1],
            self::Custom => null,
        };
    }

    /**
     * The preset matching a term, so a plan edited by hand still shows the right
     * label when it happens to line up with a standard one.
     */
    public static function forDuration(DurationUnitEnum $unit, int $value): self
    {
        foreach (self::cases() as $case) {
            $duration = $case->duration();

            if ($duration && $duration['unit'] === $unit && $duration['value'] === $value) {
                return $case;
            }
        }

        return self::Custom;
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
