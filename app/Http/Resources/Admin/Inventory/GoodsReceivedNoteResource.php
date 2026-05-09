<?php

namespace App\Http\Resources\Admin\Inventory;

use Illuminate\Http\Request;
use App\Http\Resources\Admin\PartyResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Admin\UserManagement\UserResource;
use App\Http\Resources\Admin\Purchase\PurchaseOrderResource;

class GoodsReceivedNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'grn_no' => $this->grn_no ?? '',
            'received_date' => $this->received_date ?? '',
            'purchase_order_id' => $this->purchase_order_id ?? '',
            'party_id' => $this->party_id ?? '',
            'warehouse_id' => $this->warehouse_id ?? '',
            'fiscal_year_id' => $this->fiscal_year_id ?? '',
            'supplier_invoice_no' => $this->supplier_invoice_no ?? '',
            'remarks' => $this->remarks ?? '',
            'status' => $this->status?->value ?? (string) $this->status,
            'create_user_id' => $this->create_user_id ?? '',
            'approve_user_id' => $this->approve_user_id ?? '',
            'approved_at' => $this->approved_at ?? null,
            'party' => $this->whenLoaded('party', fn () => PartyResource::make($this->party)),
            'warehouse' => $this->whenLoaded('warehouse', fn () => WarehouseResource::make($this->warehouse)),
            'purchase_order' => $this->whenLoaded('purchaseOrder', fn () => PurchaseOrderResource::make($this->purchaseOrder)),
            'create_user' => $this->whenLoaded('createUser', fn () => UserResource::make($this->createUser)),
            'approve_user' => $this->whenLoaded('approveUser', fn () => UserResource::make($this->approveUser)),
            'grn_items' => GrnItemResource::collection($this->whenLoaded('grnItems')),
        ];
    }
}
