<?php

namespace App\Http\Resources\Admin\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Admin\Concerns\MapsPartyFields;

class AdvanceReceiptResource extends JsonResource
{
    use MapsPartyFields;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'advance_no' => $this->advance_no ?? '',
            'advance_date' => $this->advance_date?->toDateString() ?? '',
            'party_id' => $this->party_id ?? '',
            'party_name' => $this->party?->name ?? '',
            ...$this->mapPartyFields($this->party),
            'payment_method' => $this->payment_method?->value ?? '',
            'payment_method_label' => $this->payment_method?->label() ?? '',
            'account_id' => $this->account_id ?? '',
            'account_name' => $this->account?->name ?? '',
            'amount' => (float) $this->amount,
            'adjusted_amount' => (float) $this->adjusted_amount,
            'balance' => $this->balance,
            'reference_no' => $this->reference_no ?? '',
            'remarks' => $this->remarks ?? '',
            'fiscal_year_id' => $this->fiscal_year_id ?? '',
            'create_user_id' => $this->create_user_id ?? '',
            'approve_user_id' => $this->approve_user_id ?? null,
            'approved_at' => $this->approved_at ?? null,
            'status' => $this->status?->value ?? '',
            'adjustments' => $this->when(
                $this->relationLoaded('adjustments'),
                fn () => $this->adjustments->map(fn ($adj) => [
                    'id' => $adj->id,
                    'invoice_id' => $adj->invoice_id,
                    'invoice_no' => $adj->invoice?->invoice_no ?? '',
                    'amount' => (float) $adj->amount,
                    'adjusted_at' => $adj->adjusted_at?->toDateTimeString() ?? null,
                ])
            ),
        ];
    }
}
