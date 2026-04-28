<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'quotation_no' => $this->quotation_no ?? '',
            'quotation_date' => $this->quotation_date ?? '',
            'expiry_date' => $this->expiry_date ?? '',
            'party_id' => $this->party_id ?? '',
            'party_name' => $this->party?->name ?? '',
            'remarks' => $this->remarks ?? '',
            'fiscal_year_id' => $this->fiscal_year_id ?? '',
            'create_user_id' => $this->create_user_id ?? '',
            'approve_user_id' => $this->approve_user_id ?? '',
            'approved_at' => $this->approved_at ?? null,
            'status' => $this->status?->value ?? '',
            'order_discount_type' => $this->discount?->type ?? 'fixed',
            'order_discount_value' => $this->discount?->value !== null
                ? round((float) $this->discount->value, 2)
                : null,
            'order_discount_amount' => round((float) ($this->discount?->amount ?? 0), 2),
            'sales_order_count' => $this->whenCounted('salesOrders'),
            'invoice_count' => $this->whenCounted('invoices'),
            'items' => QuotationItemResource::collection($this->whenLoaded('quotationItems')),
        ];
    }
}
