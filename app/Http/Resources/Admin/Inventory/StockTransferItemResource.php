<?php

namespace App\Http\Resources\Admin\Inventory;

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
            'from_warehouse_id' => $this->from_warehouse_id ?? null,
            'from_warehouse_name' => $this->whenLoaded('fromWarehouse', fn () => $this->fromWarehouse?->name ?? '', ''),
            'quantity' => $this->quantity ?? 0,
            'batch_id' => $this->batch_id,
            'batch' => $this->whenLoaded('batch', fn () => [
                'id' => $this->batch->id,
                'batch_no' => $this->batch->batch_no,
            ]),
        ];
    }
}
