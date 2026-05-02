<?php

namespace App\Http\Resources\Admin\Accounting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentVoucherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $paidFromItem = null;
        $debitItems = collect();

        if ($this->relationLoaded('journalItems')) {
            $paidFromItem = $this->journalItems->firstWhere('cr_amount', '>', 0);
            $debitItems = $this->journalItems->filter(function ($item) {
                return (float) $item->dr_amount > 0;
            });
        }

        return [
            'id' => $this->id ?? '',
            'voucher_no' => $this->voucher_no ?? '',
            'reference_no' => $this->reference_no ?? '',
            'date' => $this->date->toDateString() ?? '',
            'remarks' => $this->remarks ?? '',
            'type' => $this->type?->value ?? '',
            'fiscal_year_id' => $this->fiscal_year_id ?? '',
            'create_user_id' => $this->create_user_id ?? '',
            'approve_user_id' => $this->approve_user_id ?? '',
            'approved_at' => $this->approved_at ?? null,
            'status' => $this->status?->value ?? '',
            'paid_from_account_id' => $paidFromItem?->account_id ?? '',
            'paid_from_account' => $paidFromItem?->account?->name ?? '',
            'items' => $debitItems->map(function ($item) {
                return [
                    'id' => $item->id ?? '',
                    'account_id' => $item->account_id ?? '',
                    'account' => $item->account ? $item->account->name : '',
                    'amount' => $item->dr_amount ?? 0,
                    'remarks' => $item->remarks ?? '',
                ];
            })->values(),
        ];
    }
}
