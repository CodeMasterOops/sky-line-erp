<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Account;
use App\Models\Company;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Models\PaymentMode;
use App\Models\AccountSetting;
use Illuminate\Support\Facades\Log;
use App\Services\Accounting\AccountingPeriodGenerator;

class CompanyBootstrapService
{
    public static function runForCompany(int $companyId): void
    {
        $company = Company::query()->findOrFail($companyId);
        $fiscalYear = self::resolveFiscalYear();

        if ($company->fiscal_year_id === null) {
            $company->update(['fiscal_year_id' => $fiscalYear->id]);
        }
        $company->refresh();
        $fy = FiscalYear::query()->findOrFail($company->fiscal_year_id);

        $warehouse = config('company_bootstrap.default_warehouse');
        Warehouse::firstOrCreate(
            [
                'company_id' => $company->id,
                'code' => $warehouse['code'],
            ],
            [
                'name' => $warehouse['name'],
            ]
        );

        $branch = config('company_bootstrap.default_branch');

        Branch::firstOrCreate(
            [
                'company_id' => $company->id,
                'code' => $branch['code'],
            ],
            [
                'name' => $branch['name'],
                'is_head_office' => true,
            ]
        );

        foreach (config('company_bootstrap.default_payment_modes', []) as $mode) {
            PaymentMode::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => $mode['name'],
                ],
                [
                    'is_active' => $mode['is_active'] ?? true,
                ]
            );
        }

        $payload = self::accountSettingPayload($company->id);
        if ($payload !== []) {
            AccountSetting::updateOrCreate(
                ['company_id' => $company->id],
                $payload
            );
        } else {
            Log::warning('Company bootstrap: no account settings resolved; ensure CoaInsertService ran for this company.', [
                'company_id' => $company->id,
            ]);
        }

        AccountingPeriodGenerator::generateForCompanyIfMissing($company->id, $fy);
    }

    private static function accountSettingPayload(int $companyId): array
    {
        $map = config('company_bootstrap.account_setting_codes', []);
        $uniqueCodes = array_values(array_unique(array_filter($map)));
        if ($uniqueCodes === []) {
            return [];
        }

        $byCode = Account::query()
            ->where('company_id', $companyId)
            ->whereIn('code', $uniqueCodes)
            ->pluck('id', 'code');

        $payload = [];
        foreach ($map as $field => $code) {
            if (isset($byCode[$code])) {
                $payload[$field] = $byCode[$code];
            }
        }

        return $payload;
    }

    private static function resolveFiscalYear(): FiscalYear
    {
        $today = now()->toDateString();

        $current = FiscalYear::query()->where('is_current', true)->first();
        if ($current) {
            return $current;
        }

        $inRange = FiscalYear::query()
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->orderBy('start_date')
            ->first();
        if ($inRange) {
            return $inRange;
        }

        $year = (int) (config('company_bootstrap.fiscal_year.year') ?? now()->year);
        $start = sprintf('%d-01-01', $year);
        $end = sprintf('%d-12-31', $year);

        $existing = FiscalYear::query()
            ->where('start_date', $start)
            ->where('end_date', $end)
            ->first();
        if ($existing) {
            return $existing;
        }

        FiscalYear::query()->update(['is_current' => false]);

        return FiscalYear::query()->create([
            'year_name' => (string) $year,
            'year_code' => (string) $year,
            'start_date' => $start,
            'end_date' => $end,
            'is_current' => true,
        ]);
    }
}
