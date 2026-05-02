<?php

namespace App\Http\Resources\Admin\Purchase;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Admin\Settings\TaxResource;

class ExpenseItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'expense_id' => $this->expense_id ?? '',
            'account_id' => $this->account_id ?? '',
            'account' => $this->whenLoaded('account', function () {
                return [
                    'id' => $this->account->id,
                    'name' => $this->account->name ?? '',
                ];
            }),
            'amount' => $this->amount ?? 0,
            'line_discount_type' => $this->discount?->type ?? 'fixed',
            'line_discount_value' => $this->discount?->value !== null
                ? (float) $this->discount->value
                : 0.0,
            'tax_id' => $this->tax_id ?? '',
            'tax' => TaxResource::make($this->whenLoaded('tax')),
            'tax_amount' => $this->tax_amount ?? 0,
            'discount_amount' => $this->discount_amount ?? 0,
        ];
    }
}
