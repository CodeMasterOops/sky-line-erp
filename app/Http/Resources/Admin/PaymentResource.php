<?php

namespace App\Http\Resources\Admin;

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
            'payment_method' => $this->payment_method ?? '',
            'account_id' => $this->account_id ?? '',
            'account_name' => $this->account?->name ?? '',
            'reference_no' => $this->reference_no ?? '',
            'remarks' => $this->remarks ?? '',
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
