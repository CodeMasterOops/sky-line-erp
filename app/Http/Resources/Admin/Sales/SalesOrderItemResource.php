<?php

namespace App\Http\Resources\Admin\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Admin\Settings\TaxResource;
use App\Http\Resources\Admin\Inventory\ProductVariantResource;

class SalesOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'sales_order_id' => $this->sales_order_id ?? '',
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
            'rate' => $this->rate ?? 0,
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
