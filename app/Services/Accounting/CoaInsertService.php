<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\Company;
use Illuminate\Support\Arr;
use App\Models\AccountGroup;

class CoaInsertService
{
    public function __construct(public Company $company) {}

    public function saveCoaData(): void
    {
        $rootGroupCount = count(config('coa', []));

        // Check root groups only so a single manually-created group doesn't block
        // the full COA import for an otherwise empty company.
        if ($rootGroupCount > 0 && AccountGroup::where('company_id', $this->company->id)->whereNull('parent_id')->count() >= $rootGroupCount) {
            return;
        }

        foreach (config('coa') as $group) {
            $this->insertGroup($group, null, $this->company->id);
        }
    }

    /**
     * @param  array<string, mixed>  $group
     * @param  string|null  $inheritedType  account_type inherited from an ancestor root group.
     */
    private function insertGroup(array $group, ?int $parentId, int $companyId, ?string $inheritedType = null): void
    {
        $accountType = $group['account_type'] ?? $inheritedType;

        $accountGroup = AccountGroup::firstOrCreate(
            ['company_id' => $companyId, 'code' => $group['code']],
            Arr::except($group, ['children', 'accounts', 'code']) + [
                'parent_id' => $parentId,
                'account_type' => $accountType,
            ],
        );

        foreach ($group['accounts'] ?? [] as $account) {
            Account::firstOrCreate(
                ['company_id' => $companyId, 'code' => $account['code']],
                Arr::except($account, ['code']) + [
                    'account_group_id' => $accountGroup->id,
                ],
            );
        }

        foreach ($group['children'] ?? [] as $child) {
            $this->insertGroup($child, $accountGroup->id, $companyId, $accountType);
        }
    }
}
