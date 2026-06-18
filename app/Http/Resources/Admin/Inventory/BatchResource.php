<?php

namespace App\Http\Resources\Admin\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_variant_id' => $this->product_variant_id,
            'product_variant' => $this->whenLoaded('productVariant', fn () => [
                'id' => $this->productVariant->id,
                'name' => $this->productVariant->name,
                'sku' => $this->productVariant->sku,
                'product' => $this->productVariant->relationLoaded('product') ? [
                    'id' => $this->productVariant->product->id,
                    'name' => $this->productVariant->product->name,
                ] : null,
            ]),
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->whenLoaded('warehouse', fn () => [
                'id' => $this->warehouse->id,
                'name' => $this->warehouse->name,
                'code' => $this->warehouse->code,
            ]),
            'batch_no' => $this->batch_no,
            'lot_no' => $this->lot_no,
            'mfg_date' => $this->mfg_date,
            'expiry_date' => $this->expiry_date,
            'initial_qty' => $this->initial_qty,
            'remaining_qty' => $this->remaining_qty,
            'unit_cost' => $this->unit_cost,
            'status' => $this->status,
            'remarks' => $this->remarks,
            'created_at' => $this->created_at,
        ];
    }
}
