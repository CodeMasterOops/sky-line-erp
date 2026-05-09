<?php

namespace App\Http\Resources\Admin\Inventory;

use Illuminate\Http\Request;
use App\Http\Resources\Admin\PartyResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Admin\UserManagement\UserResource;

class DeliveryChallanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'challan_no' => $this->challan_no ?? '',
            'challan_date' => $this->challan_date ?? '',
            'party_id' => $this->party_id ?? '',
            'warehouse_id' => $this->warehouse_id ?? '',
            'fiscal_year_id' => $this->fiscal_year_id ?? '',
            'reference_type' => $this->reference_type ?? '',
            'reference_id' => $this->reference_id ?? '',
            'delivery_address' => $this->delivery_address ?? '',
            'receiver_name' => $this->receiver_name ?? '',
            'remarks' => $this->remarks ?? '',
            'status' => $this->status?->value ?? (string) $this->status,
            'create_user_id' => $this->create_user_id ?? '',
            'approve_user_id' => $this->approve_user_id ?? '',
            'approved_at' => $this->approved_at ?? null,
            'party' => $this->whenLoaded('party', fn () => PartyResource::make($this->party)),
            'warehouse' => $this->whenLoaded('warehouse', fn () => WarehouseResource::make($this->warehouse)),
            'create_user' => $this->whenLoaded('createUser', fn () => UserResource::make($this->createUser)),
            'approve_user' => $this->whenLoaded('approveUser', fn () => UserResource::make($this->approveUser)),
            'challan_items' => DeliveryChallanItemResource::collection($this->whenLoaded('challanItems')),
        ];
    }
}
