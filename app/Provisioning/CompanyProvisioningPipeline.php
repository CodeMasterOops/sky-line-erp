<?php

namespace App\Provisioning;

use App\Models\Branch;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use App\Models\CompanyProvisionLog;
use App\Services\Modules\CompanyModuleService;
use App\Provisioning\Contracts\ModuleAwareStep;
use App\Provisioning\Contracts\ProvisioningStep;

class CompanyProvisioningPipeline
{
    /** @param list<ProvisioningStep> $steps */
    public function __construct(private array $steps) {}

    public static function make(): static
    {
        return new static(app('provisioning.steps'));
    }

    public function run(Company $company): void
    {
        // Idempotency guard — skip entirely if already fully provisioned.
        $existing = CompanyProvisionLog::where('company_id', $company->id)
            ->where('status', 'complete')
            ->first();

        if ($existing) {
            return;
        }

        $log = CompanyProvisionLog::startFor($company);

        try {
            DB::transaction(function () use ($company, $log) {
                $headOffice = $this->resolveHeadOffice($company);

                foreach ($this->steps as $step) {
                    if ($step instanceof ModuleAwareStep && $this->shouldSkip($step, $company)) {
                        $log->recordStepSkipped(
                            $step->name(),
                            "The [{$step->module()}] module is not enabled for this company.",
                        );

                        continue;
                    }

                    $log->recordStep($step->name(), fn () => $step->run($company, $headOffice));
                }
            });

            $log->markComplete();
        } catch (\Throwable $e) {
            $log->markFailed($e);
            throw $e;
        }
    }

    /**
     * A module-owned step only runs for companies that run its module. Core
     * steps carry no module and always run.
     */
    private function shouldSkip(ProvisioningStep $step, Company $company): bool
    {
        if (! $step instanceof ModuleAwareStep) {
            return false;
        }

        return ! app(CompanyModuleService::class)->isEnabled($step->module(), (int) $company->id);
    }

    private function resolveHeadOffice(Company $company): Branch
    {
        $cfg = config('company_bootstrap.default_branch');

        return Branch::firstOrCreate(
            ['company_id' => $company->id, 'code' => $cfg['code']],
            ['name' => $cfg['name'], 'is_head_office' => true],
        );
    }
}
