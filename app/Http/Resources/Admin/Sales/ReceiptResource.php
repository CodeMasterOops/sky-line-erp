<?php

namespace App\Http\Resources\Admin\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Admin\Concerns\MapsPartyFields;

class ReceiptResource extends JsonResource
{
    use MapsPartyFields;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'receipt_no' => $this->receipt_no ?? '',
            'receipt_date' => $this->receipt_date ?? '',
            'party_id' => $this->party_id ?? '',
            'party_name' => $this->party?->name ?? '',
            ...$this->mapPartyFields($this->party),
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
            'tds_category' => $this->tds_category ?? null,
            'tds_rate' => $this->tds_rate ?? null,
            'tds_amount' => $this->tds_amount ? (float) $this->tds_amount : null,
            'allocations' => ReceiptAllocationResource::collection($this->whenLoaded('allocations')),
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
