<?php

namespace App\Provisioning\Steps;

use App\Models\Branch;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Provisioning\Contracts\ProvisioningStep;

class DocumentSequencesStep implements ProvisioningStep
{
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
