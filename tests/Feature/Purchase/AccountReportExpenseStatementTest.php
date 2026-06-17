<?php

use App\Models\User;
use App\Models\Account;
use App\Models\Company;
use App\Models\Journal;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\JournalItem;
use App\Models\AccountGroup;
use Laravel\Sanctum\Sanctum;
use App\Enums\JournalTypeEnum;
use App\Services\TenantService;
use App\Enums\AccountGroupTypeEnum;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function esWarmCache(): void
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
    esWarmCache();

    $this->fy = FiscalYear::create([
        'year_name' => 'FY-ES', 'year_code' => 'ES',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);
    $this->company = Company::create([
        'fiscal_year_id' => $this->fy->id,
        'company_name' => 'ES Co', 'code' => 'COES',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);
    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'ES User',
        'email' => 'es-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->expenseGroup = AccountGroup::create([
        'company_id' => $this->company->id,
        'name' => 'Operating Expenses',
        'code' => 'EXP',
        'account_type' => AccountGroupTypeEnum::Expense,
        'is_active' => true,
    ]);

    $this->salaryAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => $this->expenseGroup->id,
        'name' => 'Salary Expense',
        'code' => '5001',
        'is_active' => true,
    ]);

    $this->rentAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => $this->expenseGroup->id,
        'name' => 'Rent Expense',
        'code' => '5002',
        'is_active' => true,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

function esMakeJournal(string $date, int $accountId, float $drAmount, float $crAmount): Journal
{
    $journal = Journal::create([
        'company_id' => test()->company->id,
        'fiscal_year_id' => test()->fy->id,
        'type' => JournalTypeEnum::JOURNAL_VOUCHER,
        'voucher_no' => 'JV-ES-'.uniqid(),
        'date' => $date,
        'status' => StatusEnum::APPROVED,
        'create_user_id' => test()->user->id,
    ]);

    JournalItem::create([
        'journal_id' => $journal->id,
        'account_id' => $accountId,
        'dr_amount' => $drAmount,
        'cr_amount' => $crAmount,
    ]);

    return $journal;
}

it('returns empty rows and zero total when no expense journals exist', function () {
    $res = $this->getJson('/api/admin/account-report/expense-statement?start_date=2026-01-01&end_date=2026-12-31');

    $res->assertSuccessful();

    expect($res->json('data.summary.total_expenses'))->toEqual(0);
    expect($res->json('data.rows'))->toBeArray();
});

it('returns expense account balances for the period', function () {
    esMakeJournal('2026-03-01', $this->salaryAccount->id, 50000, 0);
    esMakeJournal('2026-04-01', $this->salaryAccount->id, 30000, 0);
    esMakeJournal('2026-05-01', $this->rentAccount->id, 20000, 0);

    $res = $this->getJson('/api/admin/account-report/expense-statement?start_date=2026-01-01&end_date=2026-12-31');

    $res->assertSuccessful();

    expect($res->json('data.summary.total_expenses'))->toEqual(100000);

    $group = collect($res->json('data.rows'))->firstWhere('name', 'Operating Expenses');
    expect($group)->not->toBeNull();

    $salary = collect($group['accounts'])->firstWhere('code', '5001');
    expect($salary['net'])->toEqual(80000);

    $rent = collect($group['accounts'])->firstWhere('code', '5002');
    expect($rent['net'])->toEqual(20000);
});

it('treats pre-period entries as opening balance and in-period entries as transactions', function () {
    esMakeJournal('2025-12-31', $this->salaryAccount->id, 90000, 0);
    esMakeJournal('2026-03-01', $this->salaryAccount->id, 10000, 0);

    $res = $this->getJson('/api/admin/account-report/expense-statement?start_date=2026-01-01&end_date=2026-12-31');

    $res->assertSuccessful();

    $group = collect($res->json('data.rows'))->firstWhere('name', 'Operating Expenses');
    $salary = collect($group['accounts'])->firstWhere('code', '5001');

    expect($salary['period_dr'])->toEqual(10000);
    expect($salary['net'])->toEqual(100000);
});

it('returns the period label in the response', function () {
    $res = $this->getJson('/api/admin/account-report/expense-statement?start_date=2026-01-01&end_date=2026-06-30');

    $res->assertSuccessful();

    expect($res->json('data.period.label'))->toContain('01-01-2026');
    expect($res->json('data.period.label'))->toContain('30-06-2026');
});
