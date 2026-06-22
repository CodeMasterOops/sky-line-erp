<?php

namespace App\Http\Resources\Admin\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DamageReportItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'damage_report_id' => $this->damage_report_id ?? '',
            'product_variant_id' => $this->product_variant_id ?? '',
            'product_variant' => ProductVariantResource::make($this->whenLoaded('productVariant')),
            'unit_id' => $this->unit_id ?? '',
            'unit' => $this->whenLoaded('unit', fn () => [
                'id' => $this->unit->id,
                'name' => $this->unit->name ?? '',
            ]),
            'quantity' => $this->quantity ?? 0,
            'unit_cost' => $this->unit_cost,
            'remarks' => $this->remarks ?? '',
            'batch_id' => $this->batch_id,
            'batch' => $this->whenLoaded('batch', fn () => [
                'id' => $this->batch->id,
                'batch_no' => $this->batch->batch_no,
                'expiry_date' => $this->batch->expiry_date?->toDateString(),
            ]),
        ];
    }
}
