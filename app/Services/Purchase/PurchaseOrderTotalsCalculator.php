<?php

namespace App\Services\Purchase;

use App\Models\Tax;
use Illuminate\Support\Collection;
use App\Enums\AmountOrPercentDiscountTypeEnum;

class PurchaseOrderTotalsCalculator
{
    /**
     * @param  array<int, array<string, mixed>>  $items  Raw request items (quantity, rate, tax_id, line_discount_*)
     * @return array{items: array<int, array<string, mixed>>, order_discount_amount: float}
     */
    public function resolveItemsAndOrderDiscount(
        array $items,
        AmountOrPercentDiscountTypeEnum $orderDiscountType,
        float $orderDiscountValue,
    ): array {
        $taxRates = $this->loadTaxRates(array_column($items, 'tax_id'));

        $lineNets = [];
        $lineDiscounts = [];
        $grosses = [];

        foreach ($items as $idx => $item) {
            $qty = (int) ($item['quantity'] ?? 0);
            $rate = (float) ($item['rate'] ?? 0);
            $gross = $qty * $rate;
            $grosses[$idx] = $gross;

            $lineType = AmountOrPercentDiscountTypeEnum::tryFromString($item['line_discount_type'] ?? null);
            $lineValue = (float) ($item['line_discount_value'] ?? 0);
            $lineDiscount = $this->resolveLineDiscountMoney($gross, $lineType, $lineValue);
            $lineDiscounts[$idx] = $lineDiscount;
            $lineNets[$idx] = max(0, $gross - $lineDiscount);
        }

        $sumNet = array_sum($lineNets);
        $orderDiscountAmount = $this->resolveOrderDiscountMoney($sumNet, $orderDiscountType, $orderDiscountValue);

        $resolvedItems = [];
        foreach ($items as $idx => $item) {
            $lineNet = $lineNets[$idx];
            $alloc = $sumNet > 0 ? $orderDiscountAmount * ($lineNet / $sumNet) : 0.0;
            $taxable = max(0, $lineNet - $alloc);
            $taxId = $item['tax_id'] ?? null;
            $taxRate = $taxRates->get((int) $taxId)?->rate ?? 0.0;
            $taxAmount = $taxable * ((float) $taxRate / 100);

            $resolvedItems[] = array_merge($item, [
                'quantity' => (int) ($item['quantity'] ?? 0),
                'rate' => (float) ($item['rate'] ?? 0),
                'discount_amount' => round($lineDiscounts[$idx], 2),
                'line_discount_type' => AmountOrPercentDiscountTypeEnum::tryFromString($item['line_discount_type'] ?? null)->value,
                'line_discount_value' => isset($item['line_discount_value']) ? round((float) $item['line_discount_value'], 2) : null,
                'tax_amount' => round($taxAmount, 2),
            ]);
        }

        return [
            'items' => $resolvedItems,
            'order_discount_amount' => round($orderDiscountAmount, 2),
        ];
    }

    private function resolveLineDiscountMoney(
        float $gross,
        AmountOrPercentDiscountTypeEnum $type,
        float $value,
    ): float {
        if ($gross <= 0) {
            return 0.0;
        }

        if ($type === AmountOrPercentDiscountTypeEnum::Percent) {
            $pct = max(0, min(100, $value));

            return min($gross * $pct / 100, $gross);
        }

        return min(max(0, $value), $gross);
    }

    private function resolveOrderDiscountMoney(
        float $sumNet,
        AmountOrPercentDiscountTypeEnum $type,
        float $value,
    ): float {
        if ($sumNet <= 0) {
            return 0.0;
        }

        if ($type === AmountOrPercentDiscountTypeEnum::Percent) {
            $pct = max(0, min(100, $value));

            return min($sumNet * $pct / 100, $sumNet);
        }

        return min(max(0, $value), $sumNet);
    }

    /**
     * @param  array<int|string|null>  $taxIds
     */
    private function loadTaxRates(array $taxIds): Collection
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn ($id) => $id !== null && $id !== '' ? (int) $id : null, $taxIds)
        )));

        if ($ids === []) {
            return collect();
        }

        return Tax::query()->whereIn('id', $ids)->get()->keyBy('id');
    }
}
