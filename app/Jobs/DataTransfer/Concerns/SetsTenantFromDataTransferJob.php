<?php

namespace App\Jobs\DataTransfer\Concerns;

use App\Models\DataTransferJob;
use App\Services\TenantService;

trait SetsTenantFromDataTransferJob
{
    protected function setTenantFromJob(DataTransferJob $job): void
    {
        TenantService::setCompanyId($job->company_id);

        if ($job->branch_id) {
            TenantService::setBranchId($job->branch_id);
        }
    }
}
