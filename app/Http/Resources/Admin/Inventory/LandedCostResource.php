<?php

namespace App\Http\Resources\Admin\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Admin\Accounting\AccountResource;

class LandedCostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'bill_id' => $this->bill_id ?? '',
            'goods_received_note_id' => $this->goods_received_note_id ?? '',
            'cost_type' => $this->cost_type ?? '',
            'description' => $this->description ?? '',
            'treatment' => $this->treatment?->value ?? (string) $this->treatment,
            'allocation_method' => $this->allocation_method?->value ?? (string) $this->allocation_method,
            'amount' => (float) ($this->amount ?? 0),
            'vat_amount' => (float) ($this->vat_amount ?? 0),
            'vat_claimable_amount' => (float) ($this->vat_claimable_amount ?? 0),
            'account_id' => $this->account_id ?? '',
            'account' => $this->whenLoaded('account', fn () => AccountResource::make($this->account)),
            'journal_id' => $this->journal_id ?? '',
            'allocations' => $this->whenLoaded('allocations', fn () => $this->allocations->map(fn ($allocation): array => [
                'id' => $allocation->id ?? '',
                'landed_cost_id' => $allocation->landed_cost_id ?? '',
                'grn_item_id' => $allocation->grn_item_id ?? '',
                'bill_item_id' => $allocation->bill_item_id ?? '',
                'stock_layer_id' => $allocation->stock_layer_id ?? '',
                'allocated_amount' => (float) ($allocation->allocated_amount ?? 0),
                'allocated_unit_cost' => (float) ($allocation->allocated_unit_cost ?? 0),
            ])),
        ];
    }
}
