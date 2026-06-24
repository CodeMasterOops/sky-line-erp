<?php

namespace App\Traits;

use App\Services\TenantService;
use Illuminate\Database\Eloquent\Builder;

trait BranchTenant
{
    public static function bootBranchTenant(): void
    {
        // The column check is done at create/query time, not here: a model can
        // boot before the table-columns cache is warm (test harness / Octane),
        // and gating the whole boot on that would permanently disable the trait
        // for that model in the process. Registering the hooks unconditionally
        // and checking the column at runtime is race-proof.

        // Resolve the branch dynamically at create / query time, not at boot,
        // so it follows the current request / job context rather than freezing
        // to whatever was set when the model first booted in the process.
        static::creating(function ($model) {
            if (! columnExists($model->getTable(), 'branch_id')) {
                return;
            }

            if (! is_null($model->branch_id)) {
                return;
            }

            // Warehouse-keyed inventory records belong to their warehouse's
            // branch, not the request context. This keeps the destination side
            // of a cross-branch stock transfer in the destination branch.
            if (! empty($model->warehouse_id)
                && ($warehouseBranchId = warehouseBranchId((int) $model->warehouse_id)) !== null) {
                $model->branch_id = $warehouseBranchId;

                return;
            }

            if ($branchId = TenantService::branchId()) {
                $model->branch_id = $branchId;
            }
        });

        static::addGlobalScope('branch_scope', function (Builder $builder) {
            $table = $builder->getModel()->getTable();

            if (! columnExists($table, 'branch_id')) {
                return;
            }

            if ($branchId = TenantService::branchId()) {
                $builder->where("{$table}.branch_id", $branchId);

                return;
            }

            // No branch header sent — scope non-admin users to their accessible branches
            // so they cannot see data from branches they are not assigned to.
            $user = auth('admin')->user();
            if ($user && ! $user->isAdmin()) {
                $branchIds = $user->accessibleBranchIds();
                $builder->whereIn("{$table}.branch_id", $branchIds->isEmpty() ? [0] : $branchIds);
            }
        });
    }

    /**
     * Restrict results to branches the authenticated user can access.
     * Used by multi-branch report queries where no specific branch is selected
     * but results must still be scoped to the user's permitted branches.
     */
    public function scopeForAccessibleBranches(Builder $query): Builder
    {
        $user = auth('admin')->user();

        if (! $user || $user->isAdmin()) {
            return $query;
        }

        $branchIds = $user->accessibleBranchIds();

        return $query->whereIn($this->getTable().'.branch_id', $branchIds->isEmpty() ? [0] : $branchIds);
    }
}
