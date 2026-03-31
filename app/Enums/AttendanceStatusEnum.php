<?php

namespace App\Enums;

enum AttendanceStatusEnum: string
{
    case PRESENT = 'present';
    case ABSENT = 'absent';
    case HALF_DAY = 'half_day';
    case LATE = 'late';
    case ON_LEAVE = 'on_leave';

    public function label(): string
    {
        return match ($this) {
            self::PRESENT => 'Present',
            self::ABSENT => 'Absent',
            self::HALF_DAY => 'Half Day',
            self::LATE => 'Late',
            self::ON_LEAVE => 'On Leave',
        };
    }
}
