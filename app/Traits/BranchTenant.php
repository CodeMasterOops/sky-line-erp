<?php

namespace App\Traits;

use App\Services\TenantService;
use Illuminate\Database\Eloquent\Builder;

trait BranchTenant
{
    public static function bootBranchTenant(): void
    {
        if (! columnExists((new self)->getTable(), 'branch_id')) {
            return;
        }

        // Resolve the branch dynamically at create / query time, not at boot,
        // so it follows the current request / job context rather than freezing
        // to whatever was set when the model first booted in the process.
        static::creating(function ($model) {
            if (is_null($model->branch_id) && ($branchId = TenantService::branchId())) {
                $model->branch_id = $branchId;
            }
        });

        static::addGlobalScope('branch_scope', function (Builder $builder) {
            if ($branchId = TenantService::branchId()) {
                $builder->where($builder->getModel()->getTable().'.branch_id', $branchId);
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
