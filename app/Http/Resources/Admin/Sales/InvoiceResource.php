<?php

namespace App\Http\Resources\Admin\Sales;

use App\Enums\StatusEnum;
use Illuminate\Http\Request;
use App\Http\Resources\Admin\PartyResource;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $totals = $this->calculateTotals();
        $payment = $this->calculatePaymentTotals($totals['grand_total']);

        return [
            'quotation_id' => $this->reference_type === \App\Models\Quotation::class ? $this->reference_id : null,
            'sales_order_id' => $this->reference_type === \App\Models\SalesOrder::class ? $this->reference_id : null,
            'id' => $this->id ?? '',
            'invoice_no' => $this->invoice_no ?? '',
            'invoice_date' => $this->invoice_date ?? '',
            'due_date' => $this->due_date ?? '',
            'reference_type' => $this->reference_type ?? '',
            'reference_id' => $this->reference_id ?? '',
            'party_id' => $this->party_id ?? '',
            'party_name' => $this->party?->name ?? '',
            'party' => $this->when(
                $this->relationLoaded('party') && $this->party,
                fn () => PartyResource::make($this->party)
            ),
            'remarks' => $this->remarks ?? '',
            'fiscal_year_id' => $this->fiscal_year_id ?? '',
            'create_user_id' => $this->create_user_id ?? '',
            'approve_user_id' => $this->approve_user_id ?? '',
            'approved_at' => $this->approved_at ?? null,
            'voided_at' => $this->voided_at ?? null,
            'status' => $this->status?->value ?? '',
            'order_discount_type' => $this->discount?->type ?? 'fixed',
            'order_discount_value' => $this->discount?->value !== null
                ? round((float) $this->discount->value, 2)
                : null,
            'order_discount_amount' => $totals['order_discount_amount'],
            'subtotal' => $totals['subtotal'],
            'discount_total' => $totals['discount_total'],
            'tax_total' => $totals['tax_total'],
            'grand_total' => $totals['grand_total'],
            'paid_total' => $payment['paid_total'],
            'due_amount' => $payment['due_amount'],
            'items' => InvoiceItemResource::collection($this->whenLoaded('invoiceItems')),
        ];
    }

    /**
     * @return array{paid_total: float|null, due_amount: float|null}
     */
    private function calculatePaymentTotals(float $grandTotal): array
    {
        if (! $this->relationLoaded('receiptAllocations')) {
            return [
                'paid_total' => null,
                'due_amount' => null,
            ];
        }

        $paid = 0.0;
        foreach ($this->receiptAllocations as $allocation) {
            $receipt = $allocation->relationLoaded('receipt') ? $allocation->receipt : null;
            if ($receipt && $receipt->status === StatusEnum::APPROVED) {
                $paid += (float) $allocation->amount;
            }
        }

        $paid = round($paid, 2);

        return [
            'paid_total' => $paid,
            'due_amount' => round(max($grandTotal - $paid, 0), 2),
        ];
    }

    private function calculateTotals(): array
    {
        $orderDiscountAmount = (float) ($this->discount?->amount ?? 0);

        if (! $this->relationLoaded('invoiceItems')) {
            return [
                'subtotal' => 0,
                'discount_total' => 0,
                'order_discount_amount' => round($orderDiscountAmount, 2),
                'tax_total' => 0,
                'grand_total' => 0,
            ];
        }

        $subtotal = 0;
        $discountTotal = 0;
        $taxTotal = 0;

        foreach ($this->invoiceItems as $item) {
            $lineSubtotal = (float) $item->quantity * (float) $item->rate;
            $subtotal += $lineSubtotal;
            $discountTotal += (float) $item->discount_amount;
            $taxTotal += (float) $item->tax_amount;
        }

        $grandTotal = $subtotal - $discountTotal - $orderDiscountAmount + $taxTotal;

        return [
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discountTotal, 2),
            'order_discount_amount' => round($orderDiscountAmount, 2),
            'tax_total' => round($taxTotal, 2),
            'grand_total' => round($grandTotal, 2),
        ];
    }
}
