<?php

namespace App\Provisioning\Steps;

use App\Models\Branch;
use App\Models\Account;
use App\Models\Company;
use App\Models\AccountSetting;
use Illuminate\Support\Facades\Log;
use App\Provisioning\Contracts\ModuleAwareStep;
use App\Provisioning\Contracts\ProvisioningStep;

class AccountSettingsStep implements ModuleAwareStep, ProvisioningStep
{
    public function name(): string
    {
        return 'AccountSettings';
    }

    public function module(): string
    {
        return 'accounting';
    }

    public function isIdempotent(): bool
    {
        return true;
    }

    public function run(Company $company, Branch $headOffice): void
    {
        $map = config('company_bootstrap.account_setting_codes', []);
        $uniqueCodes = array_values(array_unique(array_filter($map)));

        if ($uniqueCodes === []) {
            return;
        }

        $byCode = Account::query()
            ->where('company_id', $company->id)
            ->whereIn('code', $uniqueCodes)
            ->pluck('id', 'code');

        $payload = [];
        foreach ($map as $field => $code) {
            if (isset($byCode[$code])) {
                $payload[$field] = $byCode[$code];
            }
        }

        if ($payload === []) {
            Log::warning('Company provisioning: no account settings resolved — ensure ChartOfAccounts step ran first.', [
                'company_id' => $company->id,
            ]);

            return;
        }

        AccountSetting::updateOrCreate(['company_id' => $company->id], $payload);
    }
}
