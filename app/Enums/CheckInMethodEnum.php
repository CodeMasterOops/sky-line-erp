<?php

namespace App\Enums;

/**
 * How a member's visit was recorded. `manual` is the front desk; the rest are
 * hooks for hardware that may be wired up later (the schema carries a
 * `device_ref` for exactly that), no driver ships today.
 */
enum CheckInMethodEnum: string
{
    case Manual = 'manual';
    case Card = 'card';
    case Qr = 'qr';
    case Biometric = 'biometric';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Front desk',
            self::Card => 'Card',
            self::Qr => 'QR code',
            self::Biometric => 'Biometric',
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
