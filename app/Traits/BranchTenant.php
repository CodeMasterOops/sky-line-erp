<?php

namespace App\Traits;

use App\Services\TenantService;
use Illuminate\Database\Eloquent\Builder;

trait BranchTenant
{
    public static function bootBranchTenant(): void
    {
        $branchId = TenantService::branchId();

        if ($branchId && columnExists((new self)->getTable(), 'branch_id')) {
            static::creating(function ($model) use ($branchId) {
                $model->branch_id = is_null($model->branch_id) ? $branchId : $model->branch_id;
            });

            // global scope
            static::addGlobalScope('branch_scope', function (Builder $builder) use ($branchId) {
                return $builder->where('branch_id', $branchId);
            });
        }
    }
}
