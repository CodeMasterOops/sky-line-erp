<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockAdjustmentResource extends JsonResource
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
            'items' => StockAdjustmentItemResource::collection($this->whenLoaded('stockAdjustmentItems')),
        ];
    }
}
