<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;
use App\Models\CompanyProvisionLog;
use App\Provisioning\CompanyProvisioningPipeline;

class ProvisionCompanyCommand extends Command
{
    protected $signature = 'company:provision
                            {company_id : The ID of the company to provision}
                            {--force : Re-run even if already fully provisioned}';

    protected $description = 'Run (or re-run) the provisioning pipeline for a company.';

    public function handle(CompanyProvisioningPipeline $pipeline): int
    {
        $companyId = (int) $this->argument('company_id');
        $company = Company::find($companyId);

        if (! $company) {
            $this->error("Company #{$companyId} not found.");

            return self::FAILURE;
        }

        if ($this->option('force')) {
            // Mark existing complete log as superseded so the idempotency guard
            // allows a fresh run without losing the audit trail.
            CompanyProvisionLog::where('company_id', $companyId)
                ->where('status', 'complete')
                ->update(['status' => 'superseded']);
            $this->line("Marked existing complete provision log as superseded for company #{$companyId}.");
        }

        $this->line("Provisioning company #{$companyId} ({$company->company_name})…");

        try {
            $pipeline->run($company);
            $this->info("Company #{$companyId} provisioned successfully.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Provisioning failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
