<?php

namespace App\Enums;

enum BusinessTypeEnum: string
{
    case SoleProprietor = 'sole_proprietor';
    case Partnership = 'partnership';
    case PrivateLimited = 'private_limited';
    case PublicLimited = 'public_limited';
    case Ngo = 'ngo';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::SoleProprietor => 'Sole Proprietor',
            self::Partnership => 'Partnership',
            self::PrivateLimited => 'Private Limited',
            self::PublicLimited => 'Public Limited',
            self::Ngo => 'NGO',
            self::Other => 'Other',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
