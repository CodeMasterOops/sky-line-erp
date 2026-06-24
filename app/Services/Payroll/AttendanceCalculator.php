<?php

namespace App\Services\Payroll;

use Carbon\Carbon;
use App\Models\WorkSchedule;

/**
 * Derives worked hours, late/early minutes and overtime from check-in/out
 * against a company work schedule.
 */
class AttendanceCalculator
{
    /**
     * @return array{worked_hours: float, late_minutes: int, early_leaving_minutes: int, overtime_hours: float}
     */
    public function compute(?string $checkIn, ?string $checkOut, WorkSchedule $schedule): array
    {
        if (! $checkIn || ! $checkOut) {
            return ['worked_hours' => 0.0, 'late_minutes' => 0, 'early_leaving_minutes' => 0, 'overtime_hours' => 0.0];
        }

        $in = Carbon::parse($checkIn);
        $out = Carbon::parse($checkOut);
        $shiftEnd = $out->copy();
        if ($shiftEnd->lessThanOrEqualTo($in)) {
            $shiftEnd->addDay(); // overnight shift
        }

        $workedHours = round($in->diffInMinutes($shiftEnd) / 60, 2);

        $startWithGrace = Carbon::parse($schedule->start_time)->addMinutes($schedule->grace_minutes);
        $lateMinutes = $in->greaterThan($startWithGrace)
            ? (int) round($startWithGrace->diffInMinutes($in))
            : 0;

        $end = Carbon::parse($schedule->end_time);
        $earlyMinutes = $out->lessThan($end)
            ? (int) round($out->diffInMinutes($end))
            : 0;

        $overtimeHours = max(0.0, round($workedHours - (float) $schedule->standard_hours_per_day, 2));

        return [
            'worked_hours' => $workedHours,
            'late_minutes' => $lateMinutes,
            'early_leaving_minutes' => $earlyMinutes,
            'overtime_hours' => $overtimeHours,
        ];
    }
}
