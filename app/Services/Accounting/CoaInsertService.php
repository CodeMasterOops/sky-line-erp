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
        if (AccountGroup::where('company_id', $this->company->id)->count() > 0) {
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

        $accountGroup = AccountGroup::create(Arr::except($group, ['children', 'accounts']) + [
            'parent_id' => $parentId,
            'company_id' => $companyId,
            'account_type' => $accountType,
        ]);

        foreach ($group['accounts'] ?? [] as $account) {
            Account::create($account + [
                'account_group_id' => $accountGroup->id,
                'company_id' => $companyId,
            ]);
        }

        foreach ($group['children'] ?? [] as $child) {
            $this->insertGroup($child, $accountGroup->id, $companyId, $accountType);
        }
    }
}
