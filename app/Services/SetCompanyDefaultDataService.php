<?php

namespace App\Services;

use App\Models\Company;
use App\Provisioning\CompanyProvisioningPipeline;

/**
 * Thin wrapper kept for backward compatibility with existing callers
 * (controllers, seeders). New code should use CompanyProvisioningPipeline directly.
 */
class SetCompanyDefaultDataService
{
    public static function setData(Company $company): void
    {
        CompanyProvisioningPipeline::make()->run($company);
    }
}
