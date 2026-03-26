<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'reference_no' => $this->reference_no ?? '',
            'date' => $this->date ?? '',
            'from_warehouse_id' => $this->from_warehouse_id ?? '',
            'to_warehouse_id' => $this->to_warehouse_id ?? '',
            'from_warehouse' => $this->fromWarehouse ? $this->fromWarehouse->name : '',
            'to_warehouse' => $this->toWarehouse ? $this->toWarehouse->name : '',
            'remarks' => $this->remarks ?? '',
            'create_user_id' => $this->create_user_id ?? '',
            'approve_user_id' => $this->approve_user_id ?? '',
            'approved_at' => $this->approved_at ?? null,
            'status' => $this->status?->value ?? '',
            'items' => StockTransferItemResource::collection($this->whenLoaded('stockTransferItems')),
        ];
    }
}
