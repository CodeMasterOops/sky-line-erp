<?php

namespace App\Enums;

enum BloodGroupEnum: string
{
    case APositive = 'A+';
    case ANegative = 'A-';
    case BPositive = 'B+';
    case BNegative = 'B-';
    case ABPositive = 'AB+';
    case ABNegative = 'AB-';
    case OPositive = 'O+';
    case ONegative = 'O-';

    public function label(): string
    {
        return $this->value;
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
