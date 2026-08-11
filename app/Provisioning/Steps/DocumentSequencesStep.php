<?php

namespace App\Provisioning\Steps;

use App\Models\Branch;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Services\Modules\CompanyModuleService;
use App\Provisioning\Contracts\ProvisioningStep;
use App\Provisioning\Contracts\RepeatsOnModuleChange;

/**
 * Document numbering.
 *
 * Deliberately NOT a `ModuleAwareStep`: it spans several modules, so
 * all-or-nothing skipping would be wrong in both directions. It filters per
 * sequence instead — a company without Manufacturing gets no production-order
 * counter, and enabling Manufacturing later replays this step and creates it.
 *
 * `firstOrCreate` throughout, so a replay never renumbers or duplicates a
 * sequence a company is already issuing documents from.
 */
class DocumentSequencesStep implements ProvisioningStep, RepeatsOnModuleChange
{
    public function __construct(private readonly CompanyModuleService $modules) {}

    public function name(): string
    {
        return 'DocumentSequences';
    }

    public function isIdempotent(): bool
    {
        return true;
    }

    public function run(Company $company, Branch $headOffice): void
    {
        foreach (config('provisioning.document_sequences') as $sequence) {
            $moduleKey = $sequence['module'] ?? null;

            if ($moduleKey !== null && ! $this->modules->isEnabled($moduleKey, (int) $company->id)) {
                continue;
            }

            DocumentSequence::firstOrCreate(
                ['company_id' => $company->id, 'document_type' => $sequence['document_type']],
                [
                    'prefix' => $sequence['prefix'],
                    'padding' => $sequence['padding'],
                    'separator' => $sequence['separator'],
                    'reset_yearly' => $sequence['reset_yearly'],
                ],
            );
        }
    }
}
