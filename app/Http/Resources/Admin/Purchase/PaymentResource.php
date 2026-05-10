<?php

namespace App\Http\Resources\Admin\Purchase;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'payment_no' => $this->payment_no ?? '',
            'payment_date' => $this->payment_date ?? '',
            'party_id' => $this->party_id ?? '',
            'party_name' => $this->party?->name ?? '',
            'payment_mode_id' => $this->payment_mode_id ?? '',
            'payment_mode_name' => $this->paymentMode?->name ?? '',
            'account_id' => $this->account_id ?? '',
            'account_name' => $this->account?->name ?? '',
            'tds_account_id' => $this->tds_account_id ?? '',
            'tds_account_name' => $this->tdsAccount?->name ?? '',
            'reference_no' => $this->reference_no ?? '',
            'remarks' => $this->remarks ?? '',
            'tds_category' => $this->tds_category?->value ?? '',
            'tds_category_label' => $this->tds_category?->label() ?? '',
            'tds_rate' => $this->tds_rate ?? 0,
            'tds_amount' => $this->tds_amount ?? 0,
            'gross_amount' => $this->gross_amount ?? 0,
            'currency_code' => $this->currency_code ?? 'NPR',
            'exchange_rate' => $this->exchange_rate ?? 1,
            'fiscal_year_id' => $this->fiscal_year_id ?? '',
            'create_user_id' => $this->create_user_id ?? '',
            'approve_user_id' => $this->approve_user_id ?? '',
            'approved_at' => $this->approved_at ?? null,
            'status' => $this->status?->value ?? '',
            'total_amount' => $this->calculateTotal(),
            'allocations' => PaymentAllocationResource::collection($this->whenLoaded('allocations')),
        ];
    }

    private function calculateTotal(): float
    {
        if (! $this->relationLoaded('allocations')) {
            return 0;
        }

        return round($this->allocations->sum('amount'), 2);
    }
}
