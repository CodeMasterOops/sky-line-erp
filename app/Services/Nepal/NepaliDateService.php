<?php

namespace App\Services\Nepal;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Converts between Gregorian (AD) and Bikram Sambat (BS) dates.
 *
 * Reference: 1 Baisakh 2000 BS = 13 April 1943 AD
 * Each entry in BS_DATA is an array of 12 integers = days in each BS month for that year.
 */
class NepaliDateService
{
    // BS month names in Nepali (Unicode Devanagari)
    public const MONTH_NAMES_NP = [
        '', 'बैशाख', 'जेष्ठ', 'आषाढ', 'श्रावण', 'भाद्र', 'आश्विन',
        'कार्तिक', 'मंसिर', 'पौष', 'माघ', 'फाल्गुन', 'चैत्र',
    ];

    // BS month names in English
    public const MONTH_NAMES_EN = [
        '', 'Baisakh', 'Jestha', 'Ashadh', 'Shrawan', 'Bhadra', 'Ashwin',
        'Kartik', 'Mangsir', 'Poush', 'Magh', 'Falgun', 'Chaitra',
    ];

    // Nepali digit mapping
    private const NP_DIGITS = ['०', '१', '२', '३', '४', '५', '६', '७', '८', '९'];

    // BS calendar data: year => [days_in_month_1..12]
    // Coverage: 2070–2090 BS (practical range: 2013–2034 AD)
    private const BS_DATA = [
        2000 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2001 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2002 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2003 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2004 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2005 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2006 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2007 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2008 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 29, 31],
        2009 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2010 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2011 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2012 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        2013 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2014 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2015 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2016 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        2017 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2018 => [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2019 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2020 => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        2021 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2022 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
        2023 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2024 => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        2025 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2026 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2027 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2028 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2029 => [31, 31, 32, 31, 32, 30, 30, 29, 30, 29, 30, 30],
        2030 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2031 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        2032 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2033 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2034 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2035 => [30, 32, 31, 32, 31, 31, 29, 30, 30, 29, 29, 31],
        2036 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2037 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2038 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2039 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        2040 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2041 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2042 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2043 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        2044 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2045 => [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2046 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2047 => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        2048 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2049 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
        2050 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2051 => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        2052 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2053 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2054 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2055 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2056 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2057 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2058 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2059 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2060 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2061 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2062 => [31, 31, 31, 32, 31, 31, 29, 30, 29, 30, 29, 31],
        2063 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2064 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2065 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2066 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 29, 31],
        2067 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2068 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2069 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2070 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        2071 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2072 => [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2073 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2074 => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        2075 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2076 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
        2077 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2078 => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        2079 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2080 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2081 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
        2082 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2083 => [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2084 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2085 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2086 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2087 => [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2088 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2089 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2090 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    ];

    // Reference: 1 Baisakh 2000 BS = 13 April 1943 AD
    private const BS_REFERENCE_YEAR = 2000;

    private const AD_REFERENCE = '1943-04-13';

    /**
     * Convert a Gregorian (AD) date string or Carbon to a BS date array [year, month, day].
     */
    public function adToBs(string|Carbon $date): array
    {
        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);

        $refCarbon = Carbon::parse(self::AD_REFERENCE);
        $daysDiff = $refCarbon->diffInDays($carbon, false);

        if ($daysDiff < 0) {
            throw new InvalidArgumentException("Date {$carbon->toDateString()} is before the supported range (1943-04-13).");
        }

        $bsYear = self::BS_REFERENCE_YEAR;
        $bsMonth = 1;
        $bsDay = 1;

        while ($daysDiff > 0) {
            if (! isset(self::BS_DATA[$bsYear])) {
                throw new InvalidArgumentException("BS year {$bsYear} is beyond the supported range (up to 2090 BS).");
            }

            $daysInMonth = self::BS_DATA[$bsYear][$bsMonth - 1];

            if ($daysDiff < $daysInMonth - ($bsDay - 1)) {
                $bsDay += $daysDiff;
                $daysDiff = 0;
            } else {
                $daysDiff -= ($daysInMonth - ($bsDay - 1));
                $bsDay = 1;
                $bsMonth++;
                if ($bsMonth > 12) {
                    $bsMonth = 1;
                    $bsYear++;
                }
            }
        }

        return ['year' => $bsYear, 'month' => $bsMonth, 'day' => $bsDay];
    }

    /**
     * Convert a BS date [year, month, day] to a Gregorian (AD) Carbon instance.
     */
    public function bsToAd(int $bsYear, int $bsMonth, int $bsDay): Carbon
    {
        if (! isset(self::BS_DATA[$bsYear])) {
            throw new InvalidArgumentException("BS year {$bsYear} is not in the supported range (2000–2090).");
        }

        if ($bsMonth < 1 || $bsMonth > 12) {
            throw new InvalidArgumentException('BS month must be between 1 and 12.');
        }

        $maxDay = self::BS_DATA[$bsYear][$bsMonth - 1];
        if ($bsDay < 1 || $bsDay > $maxDay) {
            throw new InvalidArgumentException("BS day {$bsDay} is invalid for {$bsYear}/{$bsMonth} (max: {$maxDay}).");
        }

        // Count total days from reference point
        $totalDays = 0;

        for ($y = self::BS_REFERENCE_YEAR; $y < $bsYear; $y++) {
            if (! isset(self::BS_DATA[$y])) {
                throw new InvalidArgumentException("BS year {$y} is not in the supported range.");
            }
            foreach (self::BS_DATA[$y] as $daysInMonth) {
                $totalDays += $daysInMonth;
            }
        }

        for ($m = 1; $m < $bsMonth; $m++) {
            $totalDays += self::BS_DATA[$bsYear][$m - 1];
        }

        $totalDays += $bsDay - 1;

        return Carbon::parse(self::AD_REFERENCE)->addDays($totalDays);
    }

    /**
     * Format a BS date as a string: "2081-01-15" or with separators.
     */
    public function formatBs(int $year, int $month, int $day, string $separator = '-'): string
    {
        return sprintf('%04d%s%02d%s%02d', $year, $separator, $month, $separator, $day);
    }

    /**
     * Format a BS date as a Nepali Unicode string: "२०८१-०१-१५"
     */
    public function formatBsNepali(int $year, int $month, int $day): string
    {
        return $this->toNepaliDigits(sprintf('%04d', $year)).'-'.
               $this->toNepaliDigits(sprintf('%02d', $month)).'-'.
               $this->toNepaliDigits(sprintf('%02d', $day));
    }

    /**
     * Format full BS date with month name: "15 Baisakh 2081" or Nepali equivalent.
     */
    public function formatBsFull(int $year, int $month, int $day, bool $nepali = false): string
    {
        if ($nepali) {
            $monthName = self::MONTH_NAMES_NP[$month] ?? '';

            return $this->toNepaliDigits((string) $day).' '.$monthName.' '.$this->toNepaliDigits((string) $year);
        }

        $monthName = self::MONTH_NAMES_EN[$month] ?? '';

        return "{$day} {$monthName} {$year}";
    }

    /**
     * Convert an AD date string to BS formatted string.
     */
    public function adToBsString(string|Carbon $date, string $separator = '-'): string
    {
        $bs = $this->adToBs($date);

        return $this->formatBs($bs['year'], $bs['month'], $bs['day'], $separator);
    }

    /**
     * Parse a BS date string "2081-01-15" and return [year, month, day].
     */
    public function parseBsString(string $bsDate): array
    {
        $parts = explode('-', $bsDate);
        if (count($parts) !== 3) {
            throw new InvalidArgumentException("BS date must be in format YYYY-MM-DD, got: {$bsDate}");
        }

        return [
            'year' => (int) $parts[0],
            'month' => (int) $parts[1],
            'day' => (int) $parts[2],
        ];
    }

    /**
     * Get the number of days in a BS month.
     */
    public function daysInBsMonth(int $year, int $month): int
    {
        if (! isset(self::BS_DATA[$year])) {
            throw new InvalidArgumentException("BS year {$year} not in supported range.");
        }

        return self::BS_DATA[$year][$month - 1];
    }

    /**
     * Get BS month name in English or Nepali.
     */
    public function monthName(int $month, bool $nepali = false): string
    {
        return $nepali
            ? (self::MONTH_NAMES_NP[$month] ?? '')
            : (self::MONTH_NAMES_EN[$month] ?? '');
    }

    /**
     * Return the current date in BS.
     */
    public function today(): array
    {
        return $this->adToBs(Carbon::today());
    }

    /**
     * Convert ASCII digits to Nepali (Devanagari) digits.
     */
    public function toNepaliDigits(string $input): string
    {
        return str_replace(
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            self::NP_DIGITS,
            $input
        );
    }
}
