<?php

namespace App\Rules;

use Closure;
use App\Models\Warehouse;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a warehouse belongs to the current company and to a branch the
 * authenticated user may access. Unlike the branch-aware TRule (which limits to
 * the *active* branch), this allows any of the user's accessible branches — used
 * by the sanctioned cross-branch stock-transfer flow, where the destination
 * warehouse may live in another branch the user is authorized for.
 */
class WarehouseInAccessibleBranch implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $user = auth('admin')->user();

        $warehouse = Warehouse::withoutGlobalScopes()
            ->whereKey($value)
            ->where('company_id', $user->company_id)
            ->whereNull('deleted_at')
            ->first();

        if (! $warehouse) {
            $fail('The selected warehouse is invalid.');

            return;
        }

        if (! $user->canAccessBranch((int) $warehouse->branch_id)) {
            $fail('You do not have access to the selected warehouse\'s branch.');
        }
    }
}
