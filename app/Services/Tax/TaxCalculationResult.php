<?php

namespace App\Services\Tax;

class TaxCalculationResult
{
    /**
     * @param  float  $taxableAmount  The base amount after inclusive tax adjustment
     * @param  float  $totalTaxAmount  Sum of all computed tax amounts
     * @param  array<int, array{tax_id: int, name: string, rate: float, amount: float, is_inclusive: bool, is_compound: bool}>  $lines
     */
    public function __construct(
        public readonly float $taxableAmount,
        public readonly float $totalTaxAmount,
        public readonly array $lines = [],
    ) {}

    public function grandTotal(): float
    {
        return round($this->taxableAmount + $this->totalTaxAmount, 2);
    }

    public function toArray(): array
    {
        return [
            'taxable_amount' => $this->taxableAmount,
            'tax_amount' => $this->totalTaxAmount,
            'grand_total' => $this->grandTotal(),
            'lines' => $this->lines,
        ];
    }
}
