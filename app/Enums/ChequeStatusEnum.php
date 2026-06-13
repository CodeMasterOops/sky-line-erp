<?php

namespace App\Enums;

enum ChequeStatusEnum: string
{
    case Pending = 'pending';
    case Presented = 'presented';
    case Cleared = 'cleared';
    case Bounced = 'bounced';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Presented => 'Presented',
            self::Cleared => 'Cleared',
            self::Bounced => 'Bounced',
        };
    }
}
