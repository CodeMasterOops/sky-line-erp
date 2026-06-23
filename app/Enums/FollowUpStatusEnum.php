<?php

namespace App\Enums;

enum FollowUpStatusEnum: string
{
    case Pending = 'pending';
    case Done = 'done';
    case Missed = 'missed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Done => 'Done',
            self::Missed => 'Missed',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
