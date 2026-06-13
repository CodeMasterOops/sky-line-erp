<?php

namespace App\Http\Resources\Admin\Sales;

use Illuminate\Http\Request;
use App\Enums\TdsCategoryEnum;
use Illuminate\Http\Resources\Json\JsonResource;

class TdsChallanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'fiscal_year_id' => $this->fiscal_year_id,
            'challan_no' => $this->challan_no ?? '',
            'challan_date' => $this->challan_date?->toDateString() ?? '',
            'party_id' => $this->party_id ?? null,
            'party_name' => $this->whenLoaded('party', fn () => $this->party->name ?? '', fn () => null),
            'party' => $this->whenLoaded('party', fn () => [
                'id' => $this->party->id,
                'name' => $this->party->name ?? '',
                'pan' => $this->party->pan ?? null,
            ]),
            'period_month' => $this->period_month,
            'total_tds_amount' => $this->total_tds_amount ?? 0,
            'payment_date' => $this->payment_date?->toDateString() ?? null,
            'bank_name' => $this->bank_name ?? null,
            'remarks' => $this->remarks ?? null,
            'status' => $this->status?->value ?? 'pending',
            'status_label' => $this->status?->label() ?? 'Pending',
            'create_user_id' => $this->create_user_id,
            'created_at' => $this->created_at?->toDateTimeString(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(function ($item) {
                $deduction = $item->tdsDeduction;
                $category = null;
                if ($deduction && $deduction->tds_category) {
                    $category = $deduction->tds_category instanceof TdsCategoryEnum
                        ? $deduction->tds_category
                        : TdsCategoryEnum::tryFrom((string) $deduction->tds_category);
                }

                return [
                    'id' => $item->id,
                    'tds_deduction_id' => $item->tds_deduction_id,
                    'amount' => $item->amount,
                    'tds_deduction' => $deduction ? [
                        'id' => $deduction->id,
                        'tds_category' => $category?->value,
                        'tds_category_label' => $category?->label() ?? '',
                        'base_amount' => $deduction->base_amount,
                        'tds_rate' => $deduction->tds_rate,
                        'tds_amount' => $deduction->tds_amount,
                        'period_month' => $deduction->period_month,
                    ] : null,
                ];
            })->values()->all()),
        ];
    }
}
