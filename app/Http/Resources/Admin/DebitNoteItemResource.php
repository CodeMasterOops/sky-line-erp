<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DebitNoteItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'debit_note_id' => $this->debit_note_id ?? '',
            'product_variant_id' => $this->product_variant_id ?? '',
            'product_variant' => ProductVariantResource::make($this->whenLoaded('productVariant')),
            'warehouse_id' => $this->warehouse_id ?? '',
            'bin_id' => $this->bin_id ?? '',
            'warehouse' => $this->whenLoaded('warehouse', function () {
                return [
                    'id' => $this->warehouse->id,
                    'name' => $this->warehouse->name ?? '',
                ];
            }),
            'bin' => $this->whenLoaded('bin', function () {
                return [
                    'id' => $this->bin->id,
                    'name' => $this->bin->name ?? '',
                    'code' => $this->bin->code ?? '',
                ];
            }),
            'unit_id' => $this->unit_id ?? '',
            'unit' => $this->whenLoaded('unit', function () {
                return [
                    'id' => $this->unit->id,
                    'name' => $this->unit->name ?? '',
                ];
            }),
            'quantity' => $this->quantity ?? 0,
            'rate' => $this->rate ?? 0,
            'tax_id' => $this->tax_id ?? '',
            'tax' => TaxResource::make($this->whenLoaded('tax')),
            'tax_amount' => $this->tax_amount ?? 0,
            'discount_amount' => $this->discount_amount ?? 0,
        ];
    }
}
