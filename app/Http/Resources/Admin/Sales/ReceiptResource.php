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
            'allocations' => ReceiptAllocationResource::collection($this->whenLoaded('allocations')),
            'payments' => $this->when(
                $this->relationLoaded('receiptPayments'),
                fn () => $this->receiptPayments->map(fn ($p) => [
                    'id' => $p->id,
                    'payment_method' => $p->payment_method?->value ?? '',
                    'payment_method_label' => $p->payment_method?->label() ?? '',
                    'account_id' => $p->account_id ?? '',
                    'account_name' => $p->account?->name ?? '',
                    'amount' => (float) $p->amount,
                    'reference_no' => $p->reference_no ?? '',
                    'cheque_date' => $p->cheque_date?->toDateString() ?? null,
                    'cheque_status' => $p->cheque_status?->value ?? null,
                    'cheque_status_label' => $p->cheque_status?->label() ?? null,
                ])
            ),
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
