<?php

namespace App\Http\Requests\Concerns;

use App\Models\Bin;
use Illuminate\Validation\Validator;

class ValidatesDocumentLineBins
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public static function eachBinBelongsToThatLinesWarehouse(Validator $validator, array $items): void
    {
        foreach ($items as $i => $item) {
            $wid = isset($item['warehouse_id']) ? (int) $item['warehouse_id'] : null;
            $binId = $item['bin_id'] ?? null;
            if (! $wid || ! $binId) {
                continue;
            }
            $ok = Bin::query()
                ->whereKey((int) $binId)
                ->where('warehouse_id', $wid)
                ->exists();
            if (! $ok) {
                $validator->errors()->add(
                    "items.$i.bin_id",
                    __('The selected bin does not belong to this line\'s warehouse.'),
                );
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public static function eachBinBelongsToFormWarehouse(Validator $validator, array $items, ?int $warehouseId): void
    {
        if (! $warehouseId) {
            return;
        }
        foreach ($items as $i => $item) {
            $binId = $item['bin_id'] ?? null;
            if (! $binId) {
                continue;
            }
            $ok = Bin::query()
                ->whereKey((int) $binId)
                ->where('warehouse_id', $warehouseId)
                ->exists();
            if (! $ok) {
                $validator->errors()->add(
                    "items.$i.bin_id",
                    __('The selected bin does not belong to the adjustment warehouse.'),
                );
            }
        }
    }
}
