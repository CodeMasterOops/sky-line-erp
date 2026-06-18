<?php

namespace App\Services\Inventory;

use App\Models\UnitConversion;
use Illuminate\Validation\ValidationException;

class UnitConversionService
{
    /**
     * Convert a quantity from one unit to another using stored conversion factors.
     * Supports direct and inverse lookups.
     *
     * @throws ValidationException when no conversion path exists
     */
    public function convert(float $qty, int $fromUnitId, int $toUnitId, int $companyId): float
    {
        if ($fromUnitId === $toUnitId) {
            return $qty;
        }

        $conversion = UnitConversion::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('from_unit_id', $fromUnitId)
            ->where('to_unit_id', $toUnitId)
            ->first();

        if ($conversion) {
            return round($qty * $conversion->factor, 6);
        }

        // Try inverse
        $inverse = UnitConversion::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('from_unit_id', $toUnitId)
            ->where('to_unit_id', $fromUnitId)
            ->first();

        if ($inverse) {
            return round($qty / $inverse->factor, 6);
        }

        throw ValidationException::withMessages([
            'unit' => __("No unit conversion found from unit #{$fromUnitId} to unit #{$toUnitId}."),
        ]);
    }
}
