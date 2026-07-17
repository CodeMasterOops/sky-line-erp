<?php

namespace App\Rules;

use Closure;
use App\Models\Warehouse;
use App\Services\TenantService;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that the selected warehouse is a stock location (a leaf node), not a
 * group/parent warehouse. Warehouses that have sub-warehouses are organizational
 * containers only and must never hold or transact stock; a leaf must be chosen
 * instead. Passes silently for empty values so it composes with nullable rules.
 */
class WarehouseIsStockLocation implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $companyId = TenantService::companyId() ?? auth('admin')->user()?->company_id;

        $hasChildren = Warehouse::withoutGlobalScopes()
            ->where('parent_id', (int) $value)
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->whereNull('deleted_at')
            ->exists();

        if ($hasChildren) {
            $fail(__('The selected warehouse is a group warehouse; choose one of its sub-warehouses.'));
        }
    }
}
