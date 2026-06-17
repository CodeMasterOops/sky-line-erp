<?php

namespace App\Services\Payroll;

/**
 * Nepal progressive income-tax slab calculator (FY 2080-81 / 2081-82).
 *
 * Annual slabs (individual):
 *   0 – 5,00,000        →  1%
 *   5,00,001 – 7,00,000 → 10%
 *   7,00,001 – 20,00,000 → 20%
 *   20,00,001 – 50,00,000 → 30%
 *   > 50,00,000          → 36%
 */
class SalarySlabTaxCalculator
{
    /** @var array<int, array{limit: float, rate: float}> */
    private const SLABS = [
        ['limit' => 500_000,    'rate' => 1.0],
        ['limit' => 700_000,    'rate' => 10.0],
        ['limit' => 2_000_000,  'rate' => 20.0],
        ['limit' => 5_000_000,  'rate' => 30.0],
        ['limit' => PHP_FLOAT_MAX, 'rate' => 36.0],
    ];

    public function annualTax(float $annualIncome): float
    {
        if ($annualIncome <= 0) {
            return 0.0;
        }

        $tax = 0.0;
        $previous = 0.0;

        foreach (self::SLABS as $slab) {
            if ($annualIncome <= $previous) {
                break;
            }

            $taxableInSlab = min($annualIncome, $slab['limit']) - $previous;
            $tax += $taxableInSlab * $slab['rate'] / 100;
            $previous = $slab['limit'];
        }

        return round($tax, 2);
    }

    public function monthlyWithholding(float $monthlyGross): float
    {
        $annual = $monthlyGross * 12;

        return round($this->annualTax($annual) / 12, 2);
    }
}
