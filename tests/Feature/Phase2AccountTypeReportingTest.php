<?php

use App\Models\User;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\AccountGroup;
use Laravel\Sanctum\Sanctum;
use App\Services\TenantService;
use App\Enums\AccountGroupTypeEnum;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Services\Accounting\CoaInsertService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function phase2WarmAllTablesCache(): void
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
    phase2WarmAllTablesCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'P2 Co', 'code' => 'P2C',
        'inventory_costing_method' => 'fifo',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Acct', 'email' => 'acct-'.uniqid().'@example.com',
        'password' => bcrypt('password'), 'user_type' => UserTypeEnum::ADMIN,
    ]);

    TenantService::setCompanyId($this->company->id);
    (new CoaInsertService($this->company))->saveCoaData();

    Sanctum::actingAs($this->user, ['*'], 'admin');
});

it('seeds account_type on root groups and propagates it to descendants', function () {
    $assetsRoot = AccountGroup::withoutGlobalScopes()
        ->where('company_id', $this->company->id)->where('code', 'ASS')->first();
    $inventoryGroup = AccountGroup::withoutGlobalScopes()
        ->where('company_id', $this->company->id)->where('code', 'CA')->first();

    expect($assetsRoot->account_type)->toBe(AccountGroupTypeEnum::Asset)
        ->and($inventoryGroup->account_type)->toBe(AccountGroupTypeEnum::Asset); // child inherited

    expect(AccountGroup::withoutGlobalScopes()
        ->where('company_id', $this->company->id)->whereNull('account_type')->count())->toBe(0);
});

it('returns classified profit & loss rows', function () {
    $response = $this->getJson('/api/admin/account-report/profit-loss')->assertSuccessful();

    // Income + Expenses roots both present.
    expect($response->json('data.rows'))->toHaveCount(2)
        ->and($response->json('data.summary'))->toHaveKeys(['income', 'expense', 'net_profit']);
});

it('returns classified balance sheet rows', function () {
    $response = $this->getJson('/api/admin/account-report/balance-sheet')->assertSuccessful();

    // Assets + Liabilities + Equity roots present.
    expect($response->json('data.rows'))->toHaveCount(3);
});

it('still classifies financial statements when account_type is null (legacy data)', function () {
    // Simulate a company provisioned before account_type existed.
    AccountGroup::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->update(['account_type' => null]);

    expect($this->getJson('/api/admin/account-report/profit-loss')->assertSuccessful()->json('data.rows'))
        ->toHaveCount(2);

    expect($this->getJson('/api/admin/account-report/balance-sheet')->assertSuccessful()->json('data.rows'))
        ->toHaveCount(3);
});
