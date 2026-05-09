<?php

namespace App\Http\Resources\Admin\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GrnItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'goods_received_note_id' => $this->goods_received_note_id ?? '',
            'product_variant_id' => $this->product_variant_id ?? '',
            'purchase_order_item_id' => $this->purchase_order_item_id ?? '',
            'unit_id' => $this->unit_id ?? '',
            'ordered_qty' => (float) ($this->ordered_qty ?? 0),
            'received_qty' => (float) ($this->received_qty ?? 0),
            'unit_cost' => (float) ($this->unit_cost ?? 0),
            'batch_no' => $this->batch_no ?? '',
            'expiry_date' => $this->expiry_date ?? null,
            'product_variant' => $this->whenLoaded('productVariant', fn () => ProductVariantResource::make($this->productVariant)),
            'unit' => $this->whenLoaded('unit', fn () => UnitResource::make($this->unit)),
        ];
    }
}
