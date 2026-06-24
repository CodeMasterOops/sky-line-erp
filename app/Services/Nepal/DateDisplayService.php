<?php

namespace App\Services\Nepal;

use Carbon\Carbon;
use App\Enums\DateModeEnum;

/**
 * Formats AD dates for display according to the authenticated user's calendar
 * preference (AD vs BS). Used by server-side output such as PDFs and exports.
 */
class DateDisplayService
{
    public function __construct(private NepaliDateService $nepaliDate) {}

    /**
     * Format an AD date for display in the given (or current user's) mode.
     *
     * In BS mode this returns a full Bikram Sambat date string; in AD mode it
     * returns the Gregorian date in the requested Carbon format.
     */
    public function format(string|Carbon|null $date, ?DateModeEnum $mode = null, string $adFormat = 'd M Y'): string
    {
        if (empty($date)) {
            return '';
        }

        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);
        $mode ??= $this->currentMode();

        if ($mode === DateModeEnum::Bs) {
            $bs = $this->nepaliDate->adToBs($carbon);

            return $this->nepaliDate->formatBs($bs['year'], $bs['month'], $bs['day']);
        }

        return $carbon->format($adFormat);
    }

    /**
     * Format an AD date as a month label in the given (or current user's) mode.
     *
     * BS mode → "माघ २०८२"; AD mode → "Jan 2026". The day component is ignored
     * (the AD month bucket is labelled by its first day). Falls back to the AD
     * label when the date is outside the supported BS range.
     */
    public function monthLabel(string|Carbon|null $date, ?DateModeEnum $mode = null): string
    {
        if (empty($date)) {
            return '';
        }

        $carbon = ($date instanceof Carbon ? $date->copy() : Carbon::parse($date))->startOfMonth();
        $mode ??= $this->currentMode();

        if ($mode === DateModeEnum::Bs) {
            try {
                $bs = $this->nepaliDate->adToBs($carbon);

                return $this->nepaliDate->monthName($bs['month'], true).' '.
                    $this->nepaliDate->toNepaliDigits((string) $bs['year']);
            } catch (\InvalidArgumentException) {
                return $carbon->format('M Y');
            }
        }

        return $carbon->format('M Y');
    }

    /**
     * Resolve the calendar preference of the authenticated admin user.
     */
    public function currentMode(): DateModeEnum
    {
        return auth('admin')->user()?->date_mode ?? DateModeEnum::Bs;
    }
}
