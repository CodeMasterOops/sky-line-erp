<?php

namespace App\Http\Validation;

use App\Services\TenantService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Existence rules for branch-owned data referenced from a request.
 *
 * Branch-owned child tables (e.g. delivery_challan_items, grn_items,
 * journal_items) carry no branch_id of their own — they are scoped through
 * their parent. A raw Rule::exists on them therefore lets a user reference
 * another branch's row by id. These helpers constrain the child to a parent
 * that belongs to the active company and (when a branch is selected) branch.
 */
class BranchScopedExists
{
    /**
     * Existence rule for a via-parent child row, constrained to a parent owned
     * by the active company and active branch.
     */
    public static function child(string $childTable, string $foreignKey, string $parentTable): Exists
    {
        return Rule::exists($childTable, 'id')->where(function ($query) use ($foreignKey, $parentTable) {
            $companyId = TenantService::companyId();
            $branchId = TenantService::branchId();

            $query->whereIn($foreignKey, function ($sub) use ($parentTable, $companyId, $branchId) {
                $sub->select('id')->from($parentTable)
                    ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

                if (columnExists($parentTable, 'deleted_at')) {
                    $sub->whereNull('deleted_at');
                }
            });
        });
    }
}
