<?php

use App\Services\Payroll\SalarySlabTaxCalculator;

it('applies the 1% SST on the first single band', function () {
    expect((new SalarySlabTaxCalculator)->annualTax(300_000))->toBe(3_000.0);
});

it('uses the higher married threshold for the first band', function () {
    $calc = new SalarySlabTaxCalculator;

    // Married: 1% on the whole 600,000 = 6,000.
    expect($calc->annualTax(600_000, 'married'))->toBe(6_000.0)
        // Single on the same income: 500k×1% + 100k×10% = 15,000.
        ->and($calc->annualTax(600_000, 'single'))->toBe(15_000.0);
});

it('waives the 1% SST band for SSF contributors', function () {
    $calc = new SalarySlabTaxCalculator;

    // Single SSF: first 500k at 0%, so 300k income → 0 tax.
    expect($calc->annualTax(300_000, 'single', true))->toBe(0.0)
        // 700k single SSF: 0 on first 500k + 200k×10% = 20,000.
        ->and($calc->annualTax(700_000, 'single', true))->toBe(20_000.0);
});

it('combines married thresholds with the SSF exemption', function () {
    $calc = new SalarySlabTaxCalculator;

    // Married SSF: 0 on first 600k + 200k×10% = 20,000.
    expect($calc->annualTax(800_000, 'married', true))->toBe(20_000.0);
});

it('falls back to single slabs for an unknown marital status', function () {
    expect((new SalarySlabTaxCalculator)->annualTax(300_000, 'widowed'))->toBe(3_000.0);
});
