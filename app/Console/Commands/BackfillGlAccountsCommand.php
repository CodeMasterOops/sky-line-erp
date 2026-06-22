<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Company;
use App\Models\AccountGroup;
use App\Models\AccountSetting;
use Illuminate\Console\Command;

/**
 * Ensures every GL account referenced by config/company_bootstrap.php exists for
 * each company, and fills any AccountSetting column that is still null with the
 * matching ledger account. Existing (non-null) settings are never overwritten.
 *
 * Idempotent and safe to re-run. New companies get these accounts automatically
 * through CompanyBootstrapService; this command brings older companies in line
 * (e.g. WIP, Manufacturing Variance, Damage, POS, Supplier Advance, Suspense,
 * Write-off and Rounding accounts added in Phase 1).
 */
class BackfillGlAccountsCommand extends Command
{
    protected $signature = 'accounting:backfill-gl-accounts {--company= : Limit to a single company id} {--dry-run : Report changes without writing}';

    protected $description = 'Ensure mapped GL accounts exist and fill empty AccountSetting columns for existing companies.';

    public function handle(): int
    {
        $codeMeta = $this->buildCodeMetaFromCoa();
        $fieldToCode = config('company_bootstrap.account_setting_codes', []);

        if ($fieldToCode === []) {
            $this->error('No account_setting_codes configured in config/company_bootstrap.php.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $companies = Company::query()
            ->when($this->option('company'), fn ($q) => $q->whereKey($this->option('company')))
            ->get();

        if ($companies->isEmpty()) {
            $this->warn('No companies matched.');

            return self::SUCCESS;
        }

        $totalAccounts = 0;
        $totalSettings = 0;

        foreach ($companies as $company) {
            [$created, $filled] = $this->backfillCompany($company, $fieldToCode, $codeMeta, $dryRun);
            $totalAccounts += $created;
            $totalSettings += $filled;

            $this->line(sprintf(
                '  Company #%d %s — accounts created: %d, settings filled: %d',
                $company->id,
                $company->company_name ?? '',
                $created,
                $filled,
            ));
        }

        $this->info(sprintf(
            '%sDone. %d accounts created, %d settings filled across %d companies.',
            $dryRun ? '[dry-run] ' : '',
            $totalAccounts,
            $totalSettings,
            $companies->count(),
        ));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $fieldToCode
     * @param  array<string, array{name: string, description: ?string, category: ?string, group_code: ?string}>  $codeMeta
     * @return array{0: int, 1: int} [accounts_created, settings_filled]
     */
    private function backfillCompany(Company $company, array $fieldToCode, array $codeMeta, bool $dryRun): array
    {
        $accountsCreated = 0;
        $settingsFilled = 0;

        $setting = AccountSetting::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->first();

        $payload = [];

        foreach ($fieldToCode as $field => $code) {
            $account = Account::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('code', $code)
                ->first();

            if (! $account) {
                $meta = $codeMeta[$code] ?? null;
                if ($meta === null) {
                    $this->warn("    Skipped {$field}: code {$code} not found in config/coa.php.");

                    continue;
                }

                $groupId = $this->resolveGroupId($company->id, $meta['group_code']);
                if ($groupId === null) {
                    $this->warn("    Skipped {$field}: parent group {$meta['group_code']} missing for company #{$company->id}.");

                    continue;
                }

                if (! $dryRun) {
                    $account = Account::withoutGlobalScopes()->create([
                        'company_id' => $company->id,
                        'account_group_id' => $groupId,
                        'name' => $meta['name'],
                        'code' => $code,
                        'category' => $meta['category'],
                        'description' => $meta['description'],
                    ]);
                }

                $accountsCreated++;
            }

            $currentValue = $setting?->{$field};
            if ($currentValue === null && $account !== null) {
                $payload[$field] = $account->id;
            }
        }

        if ($payload !== [] && ! $dryRun) {
            AccountSetting::withoutGlobalScopes()->updateOrCreate(
                ['company_id' => $company->id],
                $payload,
            );
        }

        $settingsFilled += count($payload);

        return [$accountsCreated, $settingsFilled];
    }

    private function resolveGroupId(int $companyId, ?string $groupCode): ?int
    {
        if ($groupCode === null) {
            return null;
        }

        return AccountGroup::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('code', $groupCode)
            ->value('id');
    }

    /**
     * Walk config/coa.php into a flat map of account code => metadata + parent group code.
     *
     * @return array<string, array{name: string, description: ?string, category: ?string, group_code: ?string}>
     */
    private function buildCodeMetaFromCoa(): array
    {
        $meta = [];

        $walk = function (array $nodes) use (&$walk, &$meta): void {
            foreach ($nodes as $node) {
                foreach ($node['accounts'] ?? [] as $account) {
                    $meta[$account['code']] = [
                        'name' => $account['name'],
                        'description' => $account['description'] ?? null,
                        'category' => $account['category'] ?? null,
                        'group_code' => $node['code'] ?? null,
                    ];
                }

                if (! empty($node['children'])) {
                    $walk($node['children']);
                }
            }
        };

        $walk(config('coa', []));

        return $meta;
    }
}
