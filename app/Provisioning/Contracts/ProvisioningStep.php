<?php

namespace App\Provisioning\Contracts;

use App\Models\Branch;
use App\Models\Company;

interface ProvisioningStep
{
    public function name(): string;

    public function run(Company $company, Branch $headOffice): void;

    /** When true the pipeline can safely re-run this step (no duplicates created). */
    public function isIdempotent(): bool;
}
