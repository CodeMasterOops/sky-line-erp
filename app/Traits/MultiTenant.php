<?php

namespace App\Traits;

use App\Services\TenantService;
use Illuminate\Database\Eloquent\Builder;

trait MultiTenant
{
    public static function bootMultiTenant(): void
    {
        if (! columnExists((new self)->getTable(), 'company_id')) {
            return;
        }

        // Resolve the tenant dynamically at create / query time, not at boot.
        // Models boot once per process; a frozen tenant captured at boot leaks
        // across requests on long-lived workers (queues / Octane) where one
        // process serves multiple companies. Under PHP-FPM this is
        // behaviour-identical (one tenant per request).
        static::creating(function ($model) {
            if (is_null($model->company_id) && ($companyId = TenantService::companyId())) {
                $model->company_id = $companyId;
            }
        });

        static::addGlobalScope('company_scope', function (Builder $builder) {
            if ($companyId = TenantService::companyId()) {
                $builder->where('company_id', $companyId);
            }
        });
    }
}
