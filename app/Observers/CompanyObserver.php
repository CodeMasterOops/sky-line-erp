<?php

namespace App\Observers;

use App\Models\Company;
use App\Jobs\ProvisionCompanyJob;

class CompanyObserver
{
    public function created(Company $company): void
    {
        ProvisionCompanyJob::dispatch($company->id)->onQueue('provisioning');
    }
}
