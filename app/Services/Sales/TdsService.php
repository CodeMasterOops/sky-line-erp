<?php

namespace App\Services\Sales;

use App\Models\Tax;
use App\Models\Party;
use App\Models\Receipt;
use App\Models\FiscalYear;
use App\Models\TdsChallan;
use App\Models\TdsDeduction;
use App\Enums\TdsCategoryEnum;
use App\Models\TdsChallanItem;
use App\Models\ReceiptAllocation;
use Illuminate\Support\Collection;

readonly class TdsService
{
    /**
     * Record a TDS deduction when a customer pays net of TDS on a receipt allocation.
     * Called during receipt approval for each allocation that has tds_deducted > 0.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function recordDeductionOnReceipt(
        Receipt $receipt,
        ReceiptAllocation $allocation,
        Tax $tds,
    ): TdsDeduction {
        $category = $tds->tds_category instanceof TdsCategoryEnum
            ? $tds->tds_category
            : TdsCategoryEnum::from($tds->tds_category);

        $tdsRate = (float) $tds->rate;
        $baseAmount = $tdsRate > 0
            ? round((float) $allocation->tds_deducted * 100 / $tdsRate, 2)
            : (float) $allocation->amount;

        return TdsDeduction::create([
            'company_id' => $receipt->company_id,
            'fiscal_year_id' => $receipt->fiscal_year_id,
            'deductible_type' => $receipt->getMorphClass(),
            'deductible_id' => $receipt->id,
            'party_id' => $receipt->party_id,
            'tds_category' => $category->value,
            'base_amount' => $baseAmount,
            'tds_rate' => $category->rate(),
            'tds_amount' => (float) $allocation->tds_deducted,
            'period_month' => now()->month,
            'receipt_allocation_id' => $allocation->id,
        ]);
    }

    /**
     * Aggregate TDS deductions for a given party and month to prepare a challan.
     */
    public function aggregateForChallan(Party $party, int $month, FiscalYear $fy): Collection
    {
        return TdsDeduction::withoutGlobalScopes()
            ->where('company_id', $party->company_id)
            ->where('fiscal_year_id', $fy->id)
            ->where('party_id', $party->id)
            ->where('period_month', $month)
            ->whereDoesntHave('challanItem')
            ->get();
    }

    /**
     * Create a TDS challan from deductions for a party/month, linking all matching deduction records.
     */
    public function createChallan(
        int $companyId,
        int $fiscalYearId,
        int $partyId,
        int $month,
        array $deductionIds,
        array $attributes,
    ): TdsChallan {
        $deductions = TdsDeduction::withoutGlobalScopes()
            ->whereIn('id', $deductionIds)
            ->where('company_id', $companyId)
            ->where('party_id', $partyId)
            ->get();

        $total = $deductions->sum('tds_amount');

        $challan = TdsChallan::create([
            'company_id' => $companyId,
            'fiscal_year_id' => $fiscalYearId,
            'challan_no' => $attributes['challan_no'] ?? null,
            'challan_date' => $attributes['challan_date'],
            'party_id' => $partyId,
            'period_month' => $month,
            'total_tds_amount' => $total,
            'payment_date' => $attributes['payment_date'] ?? null,
            'bank_name' => $attributes['bank_name'] ?? null,
            'remarks' => $attributes['remarks'] ?? null,
            'status' => 'pending',
            'create_user_id' => auth('admin')->id(),
        ]);

        foreach ($deductions as $deduction) {
            TdsChallanItem::create([
                'tds_challan_id' => $challan->id,
                'tds_deduction_id' => $deduction->id,
                'amount' => $deduction->tds_amount,
            ]);
        }

        return $challan;
    }

    /**
     * Generate a TDS certificate summary for a party for a given month and fiscal year.
     *
     * @return array<string, mixed>
     */
    public function generateCertificate(Party $party, int $month, FiscalYear $fy): array
    {
        $deductions = TdsDeduction::withoutGlobalScopes()
            ->where('company_id', $party->company_id)
            ->where('fiscal_year_id', $fy->id)
            ->where('party_id', $party->id)
            ->where('period_month', $month)
            ->get();

        $byCategory = $deductions->groupBy('tds_category');

        $breakdown = $byCategory->map(function (Collection $items, string $category) {
            $enum = TdsCategoryEnum::from($category);

            return [
                'category' => $category,
                'label' => $enum->label(),
                'rate' => $enum->rate(),
                'base_amount' => round($items->sum('base_amount'), 2),
                'tds_amount' => round($items->sum('tds_amount'), 2),
                'revenue_code' => $enum->revenueCode(),
            ];
        })->values()->all();

        return [
            'party_id' => $party->id,
            'party_name' => $party->name,
            'party_pan' => $party->pan ?? null,
            'fiscal_year_id' => $fy->id,
            'period_month' => $month,
            'total_base_amount' => round($deductions->sum('base_amount'), 2),
            'total_tds_amount' => round($deductions->sum('tds_amount'), 2),
            'breakdown' => $breakdown,
        ];
    }
}
