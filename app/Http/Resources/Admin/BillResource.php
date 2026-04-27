<?php

namespace App\Http\Resources\Admin;

use App\Models\Tax;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $totals = $this->calculateTotals();

        return [
            'id' => $this->id ?? '',
            'bill_no' => $this->bill_no ?? '',
            'bill_date' => $this->bill_date ?? '',
            'due_date' => $this->due_date ?? '',
            'purchase_order_id' => $this->purchase_order_id ?? '',
            'party_id' => $this->party_id ?? '',
            'party_name' => $this->party?->name ?? '',
            'remarks' => $this->remarks ?? '',
            'order_discount_type' => $this->discount?->type ?? 'fixed',
            'order_discount_value' => $this->discount?->value !== null
                ? round((float) $this->discount->value, 2)
                : null,
            'order_discount_amount' => $totals['order_discount_amount'],
            'fiscal_year_id' => $this->fiscal_year_id ?? '',
            'create_user_id' => $this->create_user_id ?? '',
            'approve_user_id' => $this->approve_user_id ?? '',
            'approved_at' => $this->approved_at ?? null,
            'voided_at' => $this->voided_at ?? null,
            'status' => $this->status?->value ?? '',
            'subtotal' => $totals['subtotal'],
            'discount_total' => $totals['line_discount_total'],
            'line_discount_total' => $totals['line_discount_total'],
            'non_taxable_base' => $totals['non_taxable_base'],
            'taxable_base' => $totals['taxable_base'],
            'tax_total' => $totals['tax_total'],
            'grand_total' => $totals['grand_total'],
            'items' => BillItemResource::collection($this->whenLoaded('billItems')),
        ];
    }

    private function calculateTotals(): array
    {
        if (! $this->relationLoaded('billItems')) {
            $ord = (float) ($this->discount?->amount ?? 0);

            return [
                'subtotal' => 0,
                'line_discount_total' => 0,
                'order_discount_amount' => round($ord, 2),
                'non_taxable_base' => 0,
                'taxable_base' => 0,
                'tax_total' => 0,
                'grand_total' => 0,
            ];
        }

        $subtotal = 0.0;
        $lineDiscountTotal = 0.0;
        $taxTotal = 0.0;
        $lineNets = [];
        $itemsList = $this->billItems->all();

        foreach ($itemsList as $i => $item) {
            $lineGross = (float) $item->quantity * (float) $item->rate;
            $subtotal += $lineGross;
            $d = (float) $item->discount_amount;
            $lineDiscountTotal += $d;
            $lineNets[$i] = max(0, $lineGross - $d);
            $taxTotal += (float) $item->tax_amount;
        }

        $sumNet = array_sum($lineNets);
        $orderDiscountAmount = (float) ($this->discount?->amount ?? 0);

        $taxIds = collect($this->billItems)->pluck('tax_id')->filter()->unique()->all();
        $taxRates = $taxIds === []
            ? collect()
            : Tax::query()->whereIn('id', $taxIds)->pluck('rate', 'id');

        $nonTaxableBase = 0.0;
        $taxableBase = 0.0;
        if ($sumNet > 0) {
            foreach ($itemsList as $i => $item) {
                $alloc = $orderDiscountAmount * ($lineNets[$i] / $sumNet);
                $base = max(0, $lineNets[$i] - $alloc);
                $rate = $item->tax_id ? (float) ($taxRates[$item->tax_id] ?? 0) : 0.0;
                if ($rate > 0) {
                    $taxableBase += $base;
                } else {
                    $nonTaxableBase += $base;
                }
            }
        }

        $grandTotal = $subtotal - $lineDiscountTotal - $orderDiscountAmount + $taxTotal;

        return [
            'subtotal' => round($subtotal, 2),
            'line_discount_total' => round($lineDiscountTotal, 2),
            'order_discount_amount' => round($orderDiscountAmount, 2),
            'non_taxable_base' => round($nonTaxableBase, 2),
            'taxable_base' => round($taxableBase, 2),
            'tax_total' => round($taxTotal, 2),
            'grand_total' => round($grandTotal, 2),
        ];
    }
}
