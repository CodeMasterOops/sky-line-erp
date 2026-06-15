<?php

namespace App\Services\Inventory;

use App\Models\BillItem;

/**
 * Inventory cost excludes tax. Line net = (qty * rate) - discount_amount.
 */
class InventoryCostCalculator
{
    public static function unitCostFromBillItem(BillItem $item): float
    {
        $qty = (float) $item->quantity;
        if ($qty <= 0) {
            return 0.0;
        }

        $net = ($qty * (float) $item->rate) - (float) $item->discount_amount;

        return round($net / $qty, 4);
    }

    public static function unitCostFromGrnItem(\App\Models\GrnItem $item): float
    {
        return round((float) $item->unit_cost, 4);
    }
}
