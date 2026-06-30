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

function ledgerCategoryWarmCache(): void
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
    ledgerCategoryWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => 'LC-FY',
        'year_code' => 'LCF',
        'start_date' => '2024-07-16',
        'end_date' => '2025-07-15',
    ]);

    $this->company = Company::create([
        'company_name' => 'Ledger Category Co',
        'code' => 'LCC',
        'fiscal_year_id' => $this->fiscalYear->id,
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'LC Tester',
        'email' => 'lctest-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->assetGroup = AccountGroup::create([
        'company_id' => $this->company->id,
        'name' => 'Assets',
        'code' => 'ASS',
        'account_type' => AccountGroupTypeEnum::Asset,
    ]);

    $this->cashAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => $this->assetGroup->id,
        'name' => 'Cash in Hand',
        'code' => 'CIH',
        'category' => 'Cash',
    ]);

    $this->bankAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => $this->assetGroup->id,
        'name' => 'Bank Account 1',
        'code' => 'AB',
        'category' => 'Bank',
    ]);

    $this->bankChargesAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => $this->assetGroup->id,
        'name' => 'Bank Charges',
        'code' => 'BC',
        'category' => 'Bank Charges',
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

it('exposes category on every account in the index payload', function () {
    $response = $this->getJson('/api/admin/account?limit=1000');

    $response->assertOk();

    foreach ($response->json('data') as $account) {
        expect($account)->toHaveKey('category');
    }
});

it('returns cash accounts filterable by the Cash category', function () {
    $response = $this->getJson('/api/admin/account?limit=1000');

    $response->assertOk();

    $cashAccounts = collect($response->json('data'))
        ->filter(fn ($account) => $account['category'] === 'Cash')
        ->values();

    expect($cashAccounts)->toHaveCount(1);
    expect($cashAccounts->pluck('name')->all())->toContain('Cash in Hand');
});

it('returns bank accounts filterable by the Bank category without matching Bank Charges', function () {
    $response = $this->getJson('/api/admin/account?limit=1000');

    $response->assertOk();

    $bankAccounts = collect($response->json('data'))
        ->filter(fn ($account) => $account['category'] === 'Bank')
        ->values();

    expect($bankAccounts)->toHaveCount(1);
    expect($bankAccounts->pluck('name')->all())->toContain('Bank Account 1');
    expect($bankAccounts->pluck('name')->all())->not->toContain('Bank Charges');
});
