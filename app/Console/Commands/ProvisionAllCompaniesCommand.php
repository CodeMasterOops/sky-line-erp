<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;
use App\Models\CompanyProvisionLog;
use App\Provisioning\CompanyProvisioningPipeline;

class ProvisionAllCompaniesCommand extends Command
{
    protected $signature = 'company:provision-all
                            {--force : Re-provision even if already complete}
                            {--queue : Dispatch as queued jobs instead of running synchronously}';

    protected $description = 'Provision all companies that have not been fully provisioned yet.';

    public function handle(CompanyProvisioningPipeline $pipeline): int
    {
        $query = Company::query();

        if (! $this->option('force')) {
            $provisionedIds = CompanyProvisionLog::where('status', 'complete')
                ->pluck('company_id');

            $query->whereNotIn('id', $provisionedIds);
        }

        $companies = $query->get();

        if ($companies->isEmpty()) {
            $this->info('All companies are already provisioned.');

            return self::SUCCESS;
        }

        $this->info("Provisioning {$companies->count()} company/companies…");

        $bar = $this->output->createProgressBar($companies->count());
        $bar->start();

        $failed = 0;

        foreach ($companies as $company) {
            try {
                if ($this->option('force')) {
                    CompanyProvisionLog::where('company_id', $company->id)
                        ->where('status', 'complete')
                        ->update(['status' => 'superseded']);
                }

                if ($this->option('queue')) {
                    \App\Jobs\ProvisionCompanyJob::dispatch($company->id)->onQueue('provisioning');
                } else {
                    $pipeline->run($company);
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->newLine();
                $this->error("Company #{$company->id} ({$company->company_name}): {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        if ($failed > 0) {
            $this->warn("{$failed} company/companies failed to provision. Check logs for details.");

            return self::FAILURE;
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
