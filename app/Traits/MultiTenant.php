<?php

namespace App\Traits;

use App\Services\TenantService;
use Illuminate\Database\Eloquent\Builder;

trait MultiTenant
{
    public static function bootMultiTenant(): void
    {
        $companyId = TenantService::companyId();

        if ($companyId && columnExists((new self)->getTable(), 'company_id')) {
            static::creating(function ($model) use ($companyId) {
                $model->company_id = is_null($model->company_id) ? $companyId : $model->company_id;
            });

            // global scope
            static::addGlobalScope('company_scope', function (Builder $builder) use ($companyId) {
                return $builder->where('company_id', $companyId);
            });
        }
    }
}
