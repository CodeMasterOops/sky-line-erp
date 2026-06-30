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

function ag4WarmCache(): void
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
    ag4WarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => 'AG4-FY',
        'year_code' => 'AG4F',
        'start_date' => '2024-07-16',
        'end_date' => '2025-07-15',
    ]);

    $this->company = Company::create([
        'company_name' => 'AG4 Test Co',
        'code' => 'AG4TC',
        'fiscal_year_id' => $this->fiscalYear->id,
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'AG4 Tester',
        'email' => 'ag4test-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->assetGroup = AccountGroup::create([
        'company_id' => $this->company->id,
        'name' => 'Assets',
        'code' => 'ASS',
        'account_type' => AccountGroupTypeEnum::Asset,
    ]);

    $this->expenseGroup = AccountGroup::create([
        'company_id' => $this->company->id,
        'name' => 'Expenses',
        'code' => 'EXP',
        'account_type' => AccountGroupTypeEnum::Expense,
    ]);

    $this->assetAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => $this->assetGroup->id,
        'name' => 'Cash',
        'code' => 'CASH001',
    ]);

    $this->expenseAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => $this->expenseGroup->id,
        'name' => 'Rent Expense',
        'code' => 'RENT001',
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

// ── Trial Balance ──────────────────────────────────────────────

it('trial balance without account_group_id returns all groups', function () {
    $response = $this->getJson('/api/admin/account-report/trial-balance?start_date=2024-07-16&end_date=2025-07-15');

    $response->assertOk();
    $data = $response->json('data');
    $rowLabels = collect($data['rows'])->pluck('label')->all();

    expect($rowLabels)->toContain('Assets');
    expect($rowLabels)->toContain('Expenses');
});

it('trial balance with account_group_id returns only that group subtree', function () {
    $response = $this->getJson(
        "/api/admin/account-report/trial-balance?start_date=2024-07-16&end_date=2025-07-15&account_group_id={$this->assetGroup->id}"
    );

    $response->assertOk();
    $data = $response->json('data');
    $rowLabels = collect($data['rows'])->pluck('label')->all();

    expect($rowLabels)->toContain('Assets');
    expect($rowLabels)->not->toContain('Expenses');
});

it('trial balance rejects an invalid account_group_id', function () {
    $response = $this->getJson('/api/admin/account-report/trial-balance?start_date=2024-07-16&end_date=2025-07-15&account_group_id=99999');

    $response->assertUnprocessable();
});

// ── General Ledger ─────────────────────────────────────────────

it('general ledger scopes account_options to the subtree when account_group_id is provided', function () {
    $response = $this->getJson(
        "/api/admin/account-report/general-ledger?start_date=2024-07-16&end_date=2025-07-15&account_group_id={$this->expenseGroup->id}&account_id={$this->expenseAccount->id}"
    );

    $response->assertOk();
    $accountOptions = $response->json('data.account_options');

    $ids = collect($accountOptions)->pluck('id')->map(fn ($id) => (int) $id)->all();
    expect($ids)->toContain($this->expenseAccount->id);
    expect($ids)->not->toContain($this->assetAccount->id);
});

it('general ledger returns all accounts when no account_group_id filter', function () {
    $response = $this->getJson(
        "/api/admin/account-report/general-ledger?start_date=2024-07-16&end_date=2025-07-15&account_id={$this->assetAccount->id}"
    );

    $response->assertOk();
    $accountOptions = $response->json('data.account_options');

    $ids = collect($accountOptions)->pluck('id')->map(fn ($id) => (int) $id)->all();
    expect($ids)->toContain($this->assetAccount->id);
    expect($ids)->toContain($this->expenseAccount->id);
});

// ── Expense Statement ──────────────────────────────────────────

it('expense statement returns all expense groups when no account_group_id filter', function () {
    $subGroup = AccountGroup::create([
        'company_id' => $this->company->id,
        'parent_id' => $this->expenseGroup->id,
        'name' => 'Admin Expenses',
        'code' => 'ADM',
        'account_type' => AccountGroupTypeEnum::Expense,
    ]);

    $response = $this->getJson('/api/admin/account-report/expense-statement?start_date=2024-07-16&end_date=2025-07-15');

    $response->assertOk();
    $rows = $response->json('data.rows');
    $groupNames = collect($rows)->pluck('name')->all();

    expect($groupNames)->toContain('Expenses');
});

it('expense statement scopes to account_group_id subtree when provided', function () {
    $subGroup = AccountGroup::create([
        'company_id' => $this->company->id,
        'parent_id' => $this->expenseGroup->id,
        'name' => 'Admin Expenses',
        'code' => 'ADM',
        'account_type' => AccountGroupTypeEnum::Expense,
    ]);

    $response = $this->getJson(
        "/api/admin/account-report/expense-statement?start_date=2024-07-16&end_date=2025-07-15&account_group_id={$subGroup->id}"
    );

    $response->assertOk();
    $rows = $response->json('data.rows');
    $groupNames = collect($rows)->pluck('name')->all();

    expect($groupNames)->toContain('Admin Expenses');
    expect($groupNames)->not->toContain('Expenses');
});
