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
                $builder->where('branch_id', $branchId);
            }
        });
    }
}
