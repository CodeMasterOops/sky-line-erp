<?php

namespace App\Services\Nepal;

use App\Enums\TdsCategoryEnum;
use Illuminate\Support\Facades\DB;

/**
 * Builds the IRD e-TDS return annexure from recorded TDS deductions: the same
 * deductions that drive the deposit challan, but aggregated the way the return
 * is filed — by TDS category, by IRD revenue code, and by deductee (party).
 *
 * Effective date follows the posting journal date when present, falling back to
 * the deduction's creation date, matching the challan summary's date logic.
 */
class TdsReturnService
{
    /**
     * @return array{period:array{start_date:string, end_date:string}, by_category:list<array<string,mixed>>, by_revenue_code:list<array<string,mixed>>, by_party:list<array<string,mixed>>, summary:array{total_base:float, total_tds:float, deduction_count:int, party_count:int}}
     */
    public function annexure(int $companyId, string $startDate, string $endDate): array
    {
        $rows = DB::table('tds_deductions')
            ->leftJoin('parties', 'parties.id', '=', 'tds_deductions.party_id')
            ->leftJoin('journals', 'journals.id', '=', 'tds_deductions.journal_id')
            ->where('tds_deductions.company_id', $companyId)
            ->whereBetween(
                DB::raw('COALESCE(journals.date, DATE(tds_deductions.created_at))'),
                [$startDate, $endDate]
            )
            ->select([
                'tds_deductions.tds_category',
                'tds_deductions.party_id',
                'tds_deductions.base_amount',
                'tds_deductions.tds_amount',
                DB::raw("COALESCE(parties.name, 'N/A') as party_name"),
                DB::raw("COALESCE(parties.pan, '') as party_pan"),
            ])
            ->get();

        $byCategory = $rows
            ->groupBy('tds_category')
            ->map(function ($group, $categoryValue) {
                $category = TdsCategoryEnum::tryFrom((string) $categoryValue);

                return [
                    'category' => $categoryValue,
                    'label' => $category?->label() ?? $categoryValue,
                    'rate' => $category?->rate(),
                    'revenue_code' => $category?->revenueCode(),
                    'deduction_count' => $group->count(),
                    'party_count' => $group->pluck('party_id')->unique()->count(),
                    'base_amount' => round((float) $group->sum('base_amount'), 2),
                    'tds_amount' => round((float) $group->sum('tds_amount'), 2),
                ];
            })
            ->values()
            ->all();

        $byRevenueCode = $rows
            ->groupBy(fn ($row) => TdsCategoryEnum::tryFrom((string) $row->tds_category)?->revenueCode() ?? 'unknown')
            ->map(fn ($group, $code) => [
                'revenue_code' => $code,
                'deduction_count' => $group->count(),
                'base_amount' => round((float) $group->sum('base_amount'), 2),
                'tds_amount' => round((float) $group->sum('tds_amount'), 2),
            ])
            ->values()
            ->all();

        $byParty = $rows
            ->groupBy('party_id')
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'party_id' => $first->party_id,
                    'party_name' => $first->party_name,
                    'party_pan' => $first->party_pan,
                    'deduction_count' => $group->count(),
                    'base_amount' => round((float) $group->sum('base_amount'), 2),
                    'tds_amount' => round((float) $group->sum('tds_amount'), 2),
                ];
            })
            ->values()
            ->all();

        return [
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'by_category' => $byCategory,
            'by_revenue_code' => $byRevenueCode,
            'by_party' => $byParty,
            'summary' => [
                'total_base' => round((float) $rows->sum('base_amount'), 2),
                'total_tds' => round((float) $rows->sum('tds_amount'), 2),
                'deduction_count' => $rows->count(),
                'party_count' => $rows->pluck('party_id')->unique()->count(),
            ],
        ];
    }
}
