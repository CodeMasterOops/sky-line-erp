<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptVoucherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $depositedToItem = null;
        $creditItems = collect();

        if ($this->relationLoaded('journalItems')) {
            $depositedToItem = $this->journalItems->firstWhere('dr_amount', '>', 0);
            $creditItems = $this->journalItems->filter(function ($item) {
                return (float) $item->cr_amount > 0;
            });
        }

        return [
            'id' => $this->id ?? '',
            'voucher_no' => $this->voucher_no ?? '',
            'reference_no' => $this->reference_no ?? '',
            'date' => $this->date?->toDateString() ?? '',
            'remarks' => $this->remarks ?? '',
            'type' => $this->type?->value ?? '',
            'fiscal_year_id' => $this->fiscal_year_id ?? '',
            'create_user_id' => $this->create_user_id ?? '',
            'approve_user_id' => $this->approve_user_id ?? '',
            'approved_at' => $this->approved_at ?? null,
            'status' => $this->status?->value ?? '',
            'deposited_to_account_id' => $depositedToItem?->account_id ?? '',
            'deposited_to_account' => $depositedToItem?->account?->name ?? '',
            'items' => $creditItems->map(function ($item) {
                return [
                    'id' => $item->id ?? '',
                    'account_id' => $item->account_id ?? '',
                    'account' => $item->account ? $item->account->name : '',
                    'amount' => $item->cr_amount ?? 0,
                    'remarks' => $item->remarks ?? '',
                ];
            })->values(),
        ];
    }
}
