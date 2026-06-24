<?php

namespace App\Jobs\DataTransfer\Concerns;

use App\Models\DataTransferJob;
use App\Services\TenantService;
use App\Jobs\Middleware\ResetsTenantContext;

trait SetsTenantFromDataTransferJob
{
    protected function setTenantFromJob(DataTransferJob $job): void
    {
        TenantService::setCompanyId($job->company_id);

        if ($job->branch_id) {
            TenantService::setBranchId($job->branch_id);
        }
    }

    /**
     * Reset the tenant context after the job so the company/branch it set never
     * leaks into the next job on the worker.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new ResetsTenantContext];
    }
}
