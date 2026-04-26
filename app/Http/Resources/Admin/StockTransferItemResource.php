<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockTransferItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'stock_transfer_id' => $this->stock_transfer_id ?? '',
            'product_variant_id' => $this->product_variant_id ?? '',
            'product_variant' => ProductVariantResource::make($this->whenLoaded('productVariant')),
            'unit_id' => $this->unit_id ?? '',
            'unit' => $this->whenLoaded('unit', function () {
                return [
                    'id' => $this->unit->id,
                    'name' => $this->unit->name ?? '',
                ];
            }),
            'quantity' => $this->quantity ?? 0,
            'from_bin_id' => $this->from_bin_id ?? '',
            'to_bin_id' => $this->to_bin_id ?? '',
            'from_bin' => $this->whenLoaded('fromBin', function () {
                return [
                    'id' => $this->fromBin->id,
                    'name' => $this->fromBin->name ?? '',
                    'code' => $this->fromBin->code ?? '',
                ];
            }),
            'to_bin' => $this->whenLoaded('toBin', function () {
                return [
                    'id' => $this->toBin->id,
                    'name' => $this->toBin->name ?? '',
                    'code' => $this->toBin->code ?? '',
                ];
            }),
        ];
    }
}
