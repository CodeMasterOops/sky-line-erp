<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $totals = $this->calculateTotals();

        return [
            'id' => $this->id ?? '',
            'order_no' => $this->order_no ?? '',
            'order_date' => $this->order_date ?? '',
            'party_id' => $this->party_id ?? '',
            'party_name' => $this->party?->name ?? '',
            'remarks' => $this->remarks ?? '',
            'fiscal_year_id' => $this->fiscal_year_id ?? '',
            'create_user_id' => $this->create_user_id ?? '',
            'approve_user_id' => $this->approve_user_id ?? '',
            'approved_at' => $this->approved_at ?? null,
            'status' => $this->status?->value ?? '',
            'subtotal' => $totals['subtotal'],
            'discount_total' => $totals['discount_total'],
            'tax_total' => $totals['tax_total'],
            'grand_total' => $totals['grand_total'],
            'bill_count' => $this->whenCounted('bills'),
            'items' => PurchaseOrderItemResource::collection($this->whenLoaded('purchaseOrderItems')),
        ];
    }

    private function calculateTotals(): array
    {
        if (! $this->relationLoaded('purchaseOrderItems')) {
            return [
                'subtotal' => 0,
                'discount_total' => 0,
                'tax_total' => 0,
                'grand_total' => 0,
            ];
        }

        $subtotal = 0;
        $discountTotal = 0;
        $taxTotal = 0;

        foreach ($this->purchaseOrderItems as $item) {
            $lineSubtotal = (float) $item->quantity * (float) $item->rate;
            $subtotal += $lineSubtotal;
            $discountTotal += (float) $item->discount_amount;
            $taxTotal += (float) $item->tax_amount;
        }

        $grandTotal = $subtotal - $discountTotal + $taxTotal;

        return [
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discountTotal, 2),
            'tax_total' => round($taxTotal, 2),
            'grand_total' => round($grandTotal, 2),
        ];
    }
}
