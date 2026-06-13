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
use App\Models\JournalItem;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Enums\JournalTypeEnum;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function expenseVoidWarmAllTablesCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

beforeEach(function () {
    expenseVoidWarmAllTablesCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Expense Void Co', 'code' => 'EVC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'EV Admin',
        'email' => 'ev-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->supplier = Party::create([
        'company_id' => $this->company->id, 'name' => 'Supplier', 'code' => 'S-EV',
        'type' => PartyTypeEnum::SUPPLIER,
    ]);

    $this->apAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'AP', 'code' => 'AP-EV',
    ]);
    $this->expenseAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'Office Expense', 'code' => 'EXP-EV',
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

function makeApprovedExpenseWithJournal(object $test, string $no, float $amount, bool $withJournal = true): Expense
{
    $expense = Expense::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'party_id' => $test->supplier->id,
        'expense_no' => $no,
        'date' => '2026-03-10',
        'create_user_id' => $test->user->id,
        'status' => StatusEnum::APPROVED,
        'approved_at' => now(),
    ]);

    if ($withJournal) {
        $journal = Journal::withoutGlobalScopes()->create([
            'company_id' => $test->company->id,
            'fiscal_year_id' => $test->fiscalYear->id,
            'type' => JournalTypeEnum::EXPENSE,
            'reference_type' => $expense->getMorphClass(),
            'reference_id' => $expense->id,
            'voucher_no' => 'EXP-JV-'.$expense->id,
            'date' => '2026-03-10',
            'create_user_id' => $test->user->id,
            'status' => StatusEnum::APPROVED,
        ]);
        JournalItem::create([
            'journal_id' => $journal->id, 'account_id' => $test->expenseAccount->id,
            'dr_amount' => $amount, 'cr_amount' => 0,
        ]);
        JournalItem::create([
            'journal_id' => $journal->id, 'account_id' => $test->apAccount->id,
            'dr_amount' => 0, 'cr_amount' => $amount,
        ]);
    }

    return $expense;
}

function expenseJournalExists(object $test, Expense $expense): bool
{
    return Journal::withoutGlobalScopes()
        ->where('reference_type', $expense->getMorphClass())
        ->where('reference_id', $expense->id)
        ->whereNull('deleted_at')
        ->exists();
}

it('voids an approved expense and reverses its GL journal', function () {
    $expense = makeApprovedExpenseWithJournal($this, 'EXP-EV-1', 1000);

    expect(expenseJournalExists($this, $expense))->toBeTrue();

    $response = $this->postJson("/api/admin/expense/{$expense->id}/void");

    $response->assertSuccessful();

    expect($expense->fresh()->voided_at)->not->toBeNull();
    // GL impact gone (journal soft-deleted) but the row is retained as audit trail.
    expect(expenseJournalExists($this, $expense))->toBeFalse();
    expect(Journal::withoutGlobalScopes()->onlyTrashed()
        ->where('reference_id', $expense->id)
        ->where('reference_type', $expense->getMorphClass())
        ->exists())->toBeTrue();
});

it('is a safe no-op for an approved expense that never posted a journal', function () {
    $expense = makeApprovedExpenseWithJournal($this, 'EXP-EV-2', 0, withJournal: false);

    $response = $this->postJson("/api/admin/expense/{$expense->id}/void");

    $response->assertSuccessful();
    expect($expense->fresh()->voided_at)->not->toBeNull();
});

it('rejects voiding an expense that is not approved', function () {
    $expense = Expense::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->supplier->id,
        'expense_no' => 'EXP-EV-3',
        'date' => '2026-03-10',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $response = $this->postJson("/api/admin/expense/{$expense->id}/void");

    $response->assertStatus(422);
    expect($expense->fresh()->voided_at)->toBeNull();
});

it('rejects voiding an already-voided expense', function () {
    $expense = makeApprovedExpenseWithJournal($this, 'EXP-EV-4', 500);
    $this->postJson("/api/admin/expense/{$expense->id}/void")->assertSuccessful();

    $response = $this->postJson("/api/admin/expense/{$expense->id}/void");

    $response->assertStatus(422);
});
