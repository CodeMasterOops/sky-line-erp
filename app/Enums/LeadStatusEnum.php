<?php

namespace App\Enums;

enum LeadStatusEnum: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case DemoGiven = 'demo_given';
    case Converted = 'converted';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Contacted => 'Contacted',
            self::DemoGiven => 'Demo Given',
            self::Converted => 'Converted',
            self::Lost => 'Lost',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
