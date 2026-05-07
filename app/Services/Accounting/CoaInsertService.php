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
        $companyId = $this->company->id;
        if (AccountGroup::where('company_id', $this->company->id)->count() == 0) {
            $groups = collect(config('coa'));
            foreach ($groups as $group) {
                $accountGroup = AccountGroup::create(Arr::except($group, 'children') + [
                    'company_id' => $companyId,
                ]);

                foreach ($group['children'] ?? [] as $accSubGroup) {
                    $accountGroup1 = AccountGroup::create(Arr::except($accSubGroup, ['children', 'accounts']) + [
                        'parent_id' => $accountGroup->id,
                        'company_id' => $companyId,
                    ]);

                    foreach ($accSubGroup['children'] ?? [] as $acGroup) {
                        $accountGroup2 = AccountGroup::create(Arr::except($acGroup, ['children', 'accounts']) + [
                            'parent_id' => $accountGroup1->id,
                            'company_id' => $companyId,
                        ]);

                        foreach ($acGroup['accounts'] ?? [] as $account) {
                            Account::create($account + [
                                'account_group_id' => $accountGroup2->id,
                                'company_id' => $companyId,
                            ]);
                        }
                    }

                    foreach ($accSubGroup['accounts'] ?? [] as $account) {
                        Account::create($account + [
                            'account_group_id' => $accountGroup1->id,
                            'company_id' => $companyId,
                        ]);
                    }
                }
            }
        }
    }
}
