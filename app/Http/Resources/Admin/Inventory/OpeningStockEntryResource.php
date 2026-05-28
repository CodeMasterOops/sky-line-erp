<?php

namespace App\Http\Resources\Admin\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpeningStockEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'reference_no' => $this->reference_no ?? '',
            'date' => $this->date ?? '',
            'warehouse_id' => $this->warehouse_id ?? '',
            'warehouse' => $this->warehouse ? $this->warehouse->name : '',
            'remarks' => $this->remarks ?? '',
            'create_user_id' => $this->create_user_id ?? '',
            'approve_user_id' => $this->approve_user_id ?? '',
            'approved_at' => $this->approved_at ?? null,
            'status' => $this->status?->value ?? '',
            'product_names' => $this->whenLoaded('openingStockEntryItems', function () {
                return $this->openingStockEntryItems
                    ->map(function ($item) {
                        $variant = $item->productVariant;
                        if (! $variant) {
                            return null;
                        }

                        return $variant->variant_name ?: $variant->product?->name;
                    })
                    ->filter()
                    ->unique()
                    ->values()
                    ->implode(', ');
            }, ''),
            'items' => OpeningStockEntryItemResource::collection($this->whenLoaded('openingStockEntryItems')),
        ];
    }
}
