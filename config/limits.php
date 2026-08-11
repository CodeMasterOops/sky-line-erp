<?php

use App\Models\User;
use App\Models\Branch;
use App\Models\Member;
use App\Models\Warehouse;

/*
|--------------------------------------------------------------------------
| Plan Quotas
|--------------------------------------------------------------------------
|
| The second axis of capping (docs/module-capping-and-advanced-handling-plan.md
| Part 2). Modules answer "may this company use this feature at all?"; quotas
| answer "how much of it?".
|
| Every quota is declared here and enforced through QuotaService, so the limit,
| the count and the error message live in one place instead of being re-invented
| in each controller — `branch_limit` used to be an inline `if` in
| BranchController and was the only quota in the product.
|
| Two rules the implementation must keep:
|
|   1. A null limit means UNLIMITED. Never treat it as zero.
|   2. Quotas cap CREATION, never access. A company that ends up over a limit
|      after a downgrade keeps every row it has and every screen it had; only
|      the next `store()` is refused. Same reversibility promise as modules.
|
| Entry shape:
|   'label'  string        Human name used in messages and the usage endpoint.
|   'model'  class-string  Model counted for current usage (company-scoped).
|   'column' ?string       Column on `plans` holding the limit.
|   'path'   ?string       Key inside `plans.limits` JSON, when there is no column.
|   'module' ?string       Owning module; the quota is not evaluated when it is off.
|
*/

return [

    'branches' => [
        'label' => 'branches',
        'model' => Branch::class,
        'column' => 'branch_limit',
        'path' => null,
        'module' => null,
        // Kept verbatim from the inline check this replaced — moving a limit
        // onto the registry must not reword what users already read.
        'message' => 'Your current plan ":plan" allows a maximum of :limit branch(es). Please upgrade to add more branches.',
    ],

    'users' => [
        'label' => 'users',
        'model' => User::class,
        'column' => null,
        'path' => 'users',
        'module' => null,
    ],

    'warehouses' => [
        'label' => 'warehouses',
        'model' => Warehouse::class,
        'column' => null,
        'path' => 'warehouses',
        'module' => 'inventory',
    ],

    'gym_members' => [
        'label' => 'gym members',
        'model' => Member::class,
        'column' => null,
        'path' => 'gym_members',
        'module' => 'gym',
    ],

];
