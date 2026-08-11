<?php

namespace App\Jobs;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\Modules\CompanyModuleService;

/**
 * Brings a company's modules in line with its plan after an upgrade or a
 * downgrade.
 *
 * A downgrade only *hides* the modules the new plan does not cover — no tenant
 * data is touched, so an upgrade later restores them exactly as they were. A
 * deliberate Super Admin override (source = manual) outranks the plan and is
 * left alone.
 *
 * Queued because a plan change happens inside a request and the reconciliation
 * touches every module of the company.
 */
class ReconcileCompanyModulesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public Company $company) {}

    public function handle(CompanyModuleService $modules): void
    {
        $revoked = $modules->reconcileWithPlan($this->company);

        if ($revoked !== []) {
            Log::info('company-modules-reconciled', [
                'company_id' => $this->company->id,
                'revoked' => $revoked,
            ]);
        }
    }

    public function uniqueId(): string
    {
        return (string) $this->company->id;
    }
}
