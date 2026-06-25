<?php

namespace App\Jobs;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Provisioning\CompanyProvisioningPipeline;

class ProvisionCompanyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly int $companyId) {}

    public function handle(CompanyProvisioningPipeline $pipeline): void
    {
        $company = Company::findOrFail($this->companyId);
        $pipeline->run($company);
    }
}
