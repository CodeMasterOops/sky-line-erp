<?php

use App\Models\User;
use App\Models\Account;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\AccountGroup;
use Laravel\Sanctum\Sanctum;
use App\Services\TenantService;
use App\Enums\AccountGroupTypeEnum;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function ag1WarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
}

beforeEach(function () {
    ag1WarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => 'AG1-FY',
        'year_code' => 'AG1F',
        'start_date' => '2024-07-16',
        'end_date' => '2025-07-15',
    ]);

    $this->company = Company::create([
        'company_name' => 'AG1 Test Co',
        'code' => 'AG1TC',
        'fiscal_year_id' => $this->fiscalYear->id,
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'AG1 Tester',
        'email' => 'ag1test-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

it('AccountGroupResource exposes account_type and normal_balance for asset group', function () {
    $group = AccountGroup::create([
        'company_id' => $this->company->id,
        'name' => 'Fixed Assets',
        'code' => 'FA001',
        'account_type' => AccountGroupTypeEnum::Asset,
    ]);

    $response = $this->getJson("/api/admin/account-group/{$group->id}");

    $response->assertOk();
    $response->assertJsonPath('data.account_type', 'asset');
    $response->assertJsonPath('data.normal_balance', 'debit');
});

it('AccountGroupResource exposes account_type and normal_balance for income group', function () {
    $group = AccountGroup::create([
        'company_id' => $this->company->id,
        'name' => 'Sales Revenue',
        'code' => 'INC001',
        'account_type' => AccountGroupTypeEnum::Income,
    ]);

    $response = $this->getJson("/api/admin/account-group/{$group->id}");

    $response->assertOk();
    $response->assertJsonPath('data.account_type', 'income');
    $response->assertJsonPath('data.normal_balance', 'credit');
});

it('AccountGroupResource infers account_type from code when account_type is null', function () {
    $group = AccountGroup::create([
        'company_id' => $this->company->id,
        'name' => 'Root Expenses',
        'code' => 'EXP',
        'account_type' => null,
    ]);

    $response = $this->getJson("/api/admin/account-group/{$group->id}");

    $response->assertOk();
    $response->assertJsonPath('data.account_type', 'expense');
    $response->assertJsonPath('data.normal_balance', 'debit');
});

it('AccountGroupResource returns null for account_type when group cannot be classified', function () {
    $group = AccountGroup::create([
        'company_id' => $this->company->id,
        'name' => 'Unclassified Group',
        'code' => 'UNK001',
        'account_type' => null,
    ]);

    $response = $this->getJson("/api/admin/account-group/{$group->id}");

    $response->assertOk();
    $response->assertJsonPath('data.account_type', null);
    $response->assertJsonPath('data.normal_balance', null);
});

it('AccountResource exposes normal_balance inherited from account group type', function () {
    $group = AccountGroup::create([
        'company_id' => $this->company->id,
        'name' => 'Liability Group',
        'code' => 'LIA001',
        'account_type' => AccountGroupTypeEnum::Liability,
    ]);

    $account = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => $group->id,
        'name' => 'Bank Loan',
        'code' => 'BL001',
    ]);

    $response = $this->getJson("/api/admin/account/{$account->id}");

    $response->assertOk();
    $response->assertJsonPath('data.normal_balance', 'credit');
    $response->assertJsonPath('data.account_group.account_type', 'liability');
    $response->assertJsonPath('data.account_group.normal_balance', 'credit');
});

it('AccountResource exposes debit normal_balance for account in expense group', function () {
    $group = AccountGroup::create([
        'company_id' => $this->company->id,
        'name' => 'Operating Expenses',
        'code' => 'OPEX',
        'account_type' => AccountGroupTypeEnum::Expense,
    ]);

    $account = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => $group->id,
        'name' => 'Rent Expense',
        'code' => 'RENT001',
    ]);

    $response = $this->getJson("/api/admin/account/{$account->id}");

    $response->assertOk();
    $response->assertJsonPath('data.normal_balance', 'debit');
});
