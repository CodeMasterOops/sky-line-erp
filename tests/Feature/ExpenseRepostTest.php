<?php

use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Journal;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\ExpenseItem;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\AccountSetting;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function expenseRepostWarmAllTablesCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

function makeApprovedExpense(object $test, string $no, float $amount): Expense
{
    $expense = Expense::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'party_id' => $test->supplier->id,
        'expense_no' => $no,
        'date' => now()->toDateString(),
        'create_user_id' => $test->user->id,
        'status' => StatusEnum::APPROVED,
        'approved_at' => now(),
    ]);

    ExpenseItem::create([
        'expense_id' => $expense->id,
        'account_id' => $test->expenseAccount->id,
        'amount' => $amount,
        'tax_amount' => 0,
        'discount_amount' => 0,
    ]);

    return $expense;
}

function configureExpenseAccounts(object $test): void
{
    AccountSetting::create([
        'company_id' => $test->company->id,
        'supplier_account_id' => $test->expenseAccount->id,
    ]);
}

beforeEach(function () {
    expenseRepostWarmAllTablesCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Expense Co',
        'code' => 'EXC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Expenser',
        'email' => 'expenser-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->supplier = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Vendor',
        'code' => 'VEN-EXP',
        'type' => PartyTypeEnum::SUPPLIER,
    ]);

    $this->expenseAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Office Rent',
        'code' => 'RENT-EXP',
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

it('lists unposted approved expenses and re-posts them', function () {
    configureExpenseAccounts($this);
    $expense = makeApprovedExpense($this, 'EX-REPOST', 300);

    expect($this->getJson('/api/admin/account-report/unposted-documents')
        ->json('data.summary.expense_count'))->toBe(1);

    $this->postJson("/api/admin/account-report/expense/{$expense->id}/repost")
        ->assertSuccessful()
        ->assertJsonPath('message', 'Expense posted to the ledger successfully.');

    expect(Journal::withoutGlobalScopes()
        ->where('reference_type', (new Expense)->getMorphClass())
        ->where('reference_id', $expense->id)
        ->count())->toBe(1);

    expect($this->getJson('/api/admin/account-report/unposted-documents')
        ->json('data.summary.expense_count'))->toBe(0);
});

it('expense re-post is idempotent', function () {
    configureExpenseAccounts($this);
    $expense = makeApprovedExpense($this, 'EX-REPOST-2', 100);

    $this->postJson("/api/admin/account-report/expense/{$expense->id}/repost")->assertSuccessful();

    $this->postJson("/api/admin/account-report/expense/{$expense->id}/repost")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Expense is already posted to the ledger.');

    expect(Journal::withoutGlobalScopes()
        ->where('reference_id', $expense->id)
        ->where('reference_type', (new Expense)->getMorphClass())
        ->count())->toBe(1);
});

it('refuses to re-post an expense when the payable account is not configured', function () {
    $expense = makeApprovedExpense($this, 'EX-REPOST-3', 100);

    $this->postJson("/api/admin/account-report/expense/{$expense->id}/repost")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['account_setting']);

    expect(Journal::withoutGlobalScopes()->count())->toBe(0);
});

it('blocks expense approval when the payable account is not configured', function () {
    $expense = Expense::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->supplier->id,
        'expense_no' => 'EX-DRAFT',
        'date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    ExpenseItem::create([
        'expense_id' => $expense->id,
        'account_id' => $this->expenseAccount->id,
        'amount' => 100,
        'tax_amount' => 0,
        'discount_amount' => 0,
    ]);

    $this->postJson("/api/admin/expense/{$expense->id}/approve")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['account_setting']);

    expect(Expense::find($expense->id)->status)->not->toBe(StatusEnum::APPROVED);
    expect(Journal::withoutGlobalScopes()->count())->toBe(0);
});
