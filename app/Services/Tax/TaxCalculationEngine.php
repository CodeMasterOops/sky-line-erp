<?php

namespace App\Services\Tax;

use App\Models\Tax;
use App\Models\TaxGroup;
use App\Models\PartyTaxExemption;
use Illuminate\Support\Collection;

class TaxCalculationEngine
{
    /**
     * Calculate taxes for a set of tax records against a base amount.
     *
     * Processes taxes in sequence order and supports:
     *  - Exclusive (default): tax = base × rate / 100
     *  - Inclusive: taxable = price / (1 + rate/100), tax = price - taxable
     *  - Compound: each subsequent tax applies to base + all prior taxes
     *  - Party exemptions: skips any tax the party is exempt from on the given date
     *
     * @param  float  $baseAmount  Pre-tax line amount (qty × rate − line discount)
     * @param  Collection<int, Tax>  $taxes  Ordered by sequence
     * @param  int|null  $partyId  Used to check active exemptions
     * @param  string|null  $exemptionDate  ISO date for exemption validity check (defaults to today)
     */
    public function calculate(
        float $baseAmount,
        Collection $taxes,
        ?int $partyId = null,
        ?string $exemptionDate = null,
    ): TaxCalculationResult {
        $date = $exemptionDate ?? now()->toDateString();

        $exemptTaxIds = $this->resolveExemptTaxIds($partyId, $taxes->pluck('id')->all(), $date);

        $taxableAmount = $baseAmount;
        $runningBase = $baseAmount;
        $lines = [];
        $totalTaxAmount = 0.0;

        foreach ($taxes->sortBy('sequence') as $tax) {
            if (in_array($tax->id, $exemptTaxIds, true)) {
                continue;
            }

            $basis = $tax->is_compound ? $runningBase : $baseAmount;

            if ($tax->calculation_type === 'fixed') {
                $taxAmount = round((float) $tax->rate, 2);
            } elseif ($tax->is_inclusive) {
                // Inclusive: price already contains tax — extract it.
                $taxAmount = round($basis - $basis / (1 + $tax->rate / 100), 2);
                // Inclusive tax reduces the reported taxable base.
                $taxableAmount = round($taxableAmount - $taxAmount, 2);
            } else {
                $taxAmount = round($basis * ($tax->rate / 100), 2);
            }

            $runningBase = round($runningBase + $taxAmount, 2);
            $totalTaxAmount = round($totalTaxAmount + $taxAmount, 2);

            $lines[] = [
                'tax_id' => $tax->id,
                'name' => $tax->name,
                'rate' => $tax->rate,
                'amount' => $taxAmount,
                'is_inclusive' => $tax->is_inclusive,
                'is_compound' => $tax->is_compound,
                'gl_account_id' => $tax->gl_account_id,
            ];
        }

        return new TaxCalculationResult(
            taxableAmount: round($taxableAmount, 2),
            totalTaxAmount: round($totalTaxAmount, 2),
            lines: $lines,
        );
    }

    /**
     * Convenience wrapper that loads taxes from a TaxGroup and delegates to calculate().
     */
    public function calculateForGroup(
        float $baseAmount,
        TaxGroup $group,
        ?int $partyId = null,
        ?string $exemptionDate = null,
    ): TaxCalculationResult {
        $taxes = $group->taxes()->withoutGlobalScopes()->get();

        return $this->calculate($baseAmount, $taxes, $partyId, $exemptionDate);
    }

    /**
     * @param  int[]  $taxIds
     * @return int[]
     */
    private function resolveExemptTaxIds(?int $partyId, array $taxIds, string $date): array
    {
        if (! $partyId || empty($taxIds)) {
            return [];
        }

        return PartyTaxExemption::withoutGlobalScopes()
            ->where('party_id', $partyId)
            ->whereIn('tax_id', $taxIds)
            ->where(function ($q) use ($date) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $date);
            })
            ->where(function ($q) use ($date) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $date);
            })
            ->pluck('tax_id')
            ->all();
    }
}
