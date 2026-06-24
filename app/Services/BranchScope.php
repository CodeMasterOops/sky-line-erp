<?php

namespace App\Services;

/**
 * Applies branch isolation to raw query builders that bypass the Eloquent
 * global scope (reports, dashboards, reconciliations using DB:: / joins /
 * withoutGlobalScopes).
 *
 * Mirrors the App\Traits\BranchTenant scope exactly:
 *   - a selected branch  -> limit to that branch;
 *   - non-admin, none     -> limit to the user's accessible branches;
 *   - admin, none         -> unscoped (company-wide consolidated view).
 */
class BranchScope
{
    /**
     * @template TQuery
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public static function apply($query, string $column = 'branch_id')
    {
        $branchId = TenantService::branchId();

        if ($branchId !== null) {
            return $query->where($column, $branchId);
        }

        $user = auth('admin')->user();

        if ($user && ! $user->isAdmin()) {
            $branchIds = $user->accessibleBranchIds();

            return $query->whereIn($column, $branchIds->isEmpty() ? [0] : $branchIds->all());
        }

        return $query;
    }
}
