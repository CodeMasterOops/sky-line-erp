<?php

namespace App\Http\Resources\Admin\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditNoteChargeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'charge_type' => $this->charge_type?->value ?? $this->charge_type,
            'account_id' => $this->account_id,
            'amount' => round((float) $this->amount, 2),
            'tax_id' => $this->tax_id,
            'tax_amount' => round((float) $this->tax_amount, 2),
        ];
    }
}
