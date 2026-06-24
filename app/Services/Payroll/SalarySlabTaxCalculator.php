<?php

namespace App\Services\Payroll;

/**
 * Nepal progressive income-tax slab calculator (FY 2081-82).
 *
 * Annual slabs differ by marital status. The lowest band is the 1% Social
 * Security Tax (SST), which is waived (0%) for employees contributing to the
 * Social Security Fund (SSF).
 *
 * Single:
 *   0 – 5,00,000          →  1% (0% if SSF)
 *   5,00,001 – 7,00,000   → 10%
 *   7,00,001 – 20,00,000  → 20%
 *   20,00,001 – 50,00,000 → 30%
 *   > 50,00,000           → 36%
 *
 * Married:
 *   0 – 6,00,000          →  1% (0% if SSF)
 *   6,00,001 – 8,00,000   → 10%
 *   8,00,001 – 21,00,000  → 20%
 *   21,00,001 – 50,00,000 → 30%
 *   > 50,00,000           → 36%
 */
class SalarySlabTaxCalculator
{
    /** @var array<string, array<int, array{limit: float, rate: float}>> */
    private const SLABS = [
        'single' => [
            ['limit' => 500_000,    'rate' => 1.0],
            ['limit' => 700_000,    'rate' => 10.0],
            ['limit' => 2_000_000,  'rate' => 20.0],
            ['limit' => 5_000_000,  'rate' => 30.0],
            ['limit' => PHP_FLOAT_MAX, 'rate' => 36.0],
        ],
        'married' => [
            ['limit' => 600_000,    'rate' => 1.0],
            ['limit' => 800_000,    'rate' => 10.0],
            ['limit' => 2_100_000,  'rate' => 20.0],
            ['limit' => 5_000_000,  'rate' => 30.0],
            ['limit' => PHP_FLOAT_MAX, 'rate' => 36.0],
        ],
    ];

    public function annualTax(float $annualIncome, string $maritalStatus = 'single', bool $ssfContributor = false): float
    {
        if ($annualIncome <= 0) {
            return 0.0;
        }

        $slabs = self::SLABS[$maritalStatus] ?? self::SLABS['single'];

        $tax = 0.0;
        $previous = 0.0;

        foreach ($slabs as $index => $slab) {
            if ($annualIncome <= $previous) {
                break;
            }

            // The first band is the 1% SST; SSF contributors are exempt from it.
            $rate = ($index === 0 && $ssfContributor) ? 0.0 : $slab['rate'];

            $taxableInSlab = min($annualIncome, $slab['limit']) - $previous;
            $tax += $taxableInSlab * $rate / 100;
            $previous = $slab['limit'];
        }

        return round($tax, 2);
    }

    public function monthlyWithholding(float $monthlyGross, string $maritalStatus = 'single', bool $ssfContributor = false): float
    {
        $annual = $monthlyGross * 12;

        return round($this->annualTax($annual, $maritalStatus, $ssfContributor) / 12, 2);
    }
}
