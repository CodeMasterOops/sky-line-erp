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
use App\Models\AccountSetting;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function closingWarmAllTablesCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $tables[$table] = Schema::getColumnListing($table);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

beforeEach(function () {
    closingWarmAllTablesCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Closing Co', 'code' => 'CLOSE',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Closer',
        'email' => 'close-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $incomeGroup = AccountGroup::create([
        'company_id' => $this->company->id, 'parent_id' => null, 'name' => 'Income', 'code' => 'INC-G',
    ]);
    $expenseGroup = AccountGroup::create([
        'company_id' => $this->company->id, 'parent_id' => null, 'name' => 'Expenses', 'code' => 'EXP-G',
    ]);

    $this->salesAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => $incomeGroup->id, 'name' => 'Sales', 'code' => 'SALES-CL',
    ]);
    $this->rentAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => $expenseGroup->id, 'name' => 'Rent', 'code' => 'RENT-CL',
    ]);
    // Asset/cash account, not under income/expenses — must NOT be closed.
    $this->cashAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null, 'name' => 'Cash', 'code' => 'CASH-CL',
    ]);
    $this->retainedEarnings = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null, 'name' => 'Retained Earnings', 'code' => 'RE-CL',
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

function configureRetainedEarnings(object $test): void
{
    AccountSetting::create([
        'company_id' => $test->company->id,
        'retained_earnings_account_id' => $test->retainedEarnings->id,
    ]);
}

function postJournal(object $test, array $lines, string $voucher): void
{
    $journal = Journal::withoutGlobalScopes()->create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'type' => JournalTypeEnum::JOURNAL_VOUCHER,
        'voucher_no' => $voucher,
        'date' => '2026-03-10',
        'create_user_id' => $test->user->id,
        'status' => StatusEnum::APPROVED,
    ]);
    foreach ($lines as $line) {
        JournalItem::create([
            'journal_id' => $journal->id,
            'account_id' => $line[0],
            'dr_amount' => $line[1],
            'cr_amount' => $line[2],
        ]);
    }
}

function accountNet(object $test, int $accountId): float
{
    $row = JournalItem::query()
        ->join('journals', 'journals.id', '=', 'journal_items.journal_id')
        ->where('journals.company_id', $test->company->id)
        ->whereNull('journals.deleted_at')
        ->where('journal_items.account_id', $accountId)
        ->selectRaw('COALESCE(SUM(journal_items.dr_amount - journal_items.cr_amount), 0) as net')
        ->value('net');

    return round((float) $row, 2);
}

it('posts a closing entry that zeroes nominal accounts and books net profit to retained earnings', function () {
    configureRetainedEarnings($this);

    // Income: CR Sales 1000; Expense: DR Rent 300. Net profit = 700.
    postJournal($this, [[$this->cashAccount->id, 1000, 0], [$this->salesAccount->id, 0, 1000]], 'JV-SALE');
    postJournal($this, [[$this->rentAccount->id, 300, 0], [$this->cashAccount->id, 0, 300]], 'JV-RENT');

    $response = $this->postJson('/api/admin/accounting-period/closing-entry', [
        'fiscal_year_id' => $this->fiscalYear->id,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.posted', true)
        ->assertJsonPath('data.net_profit', 700)
        ->assertJsonPath('data.accounts_closed', 2);

    // Nominal accounts now net to zero over the fiscal year.
    expect(accountNet($this, $this->salesAccount->id))->toBe(0.0);
    expect(accountNet($this, $this->rentAccount->id))->toBe(0.0);
    // Retained earnings carries the net profit as a credit balance (net dr-cr = -700).
    expect(accountNet($this, $this->retainedEarnings->id))->toBe(-700.0);
    // The asset account is untouched by the close.
    expect(accountNet($this, $this->cashAccount->id))->toBe(700.0);
});

it('books a net loss as a debit to retained earnings', function () {
    configureRetainedEarnings($this);

    // Income 200, Expense 500 → net loss 300.
    postJournal($this, [[$this->cashAccount->id, 200, 0], [$this->salesAccount->id, 0, 200]], 'JV-SALE2');
    postJournal($this, [[$this->rentAccount->id, 500, 0], [$this->cashAccount->id, 0, 500]], 'JV-RENT2');

    $response = $this->postJson('/api/admin/accounting-period/closing-entry', [
        'fiscal_year_id' => $this->fiscalYear->id,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.net_profit', -300);

    // Loss debited to retained earnings → net dr-cr = +300.
    expect(accountNet($this, $this->retainedEarnings->id))->toBe(300.0);
    expect(accountNet($this, $this->salesAccount->id))->toBe(0.0);
    expect(accountNet($this, $this->rentAccount->id))->toBe(0.0);
});

it('is idempotent — a second closing entry is rejected', function () {
    configureRetainedEarnings($this);
    postJournal($this, [[$this->cashAccount->id, 1000, 0], [$this->salesAccount->id, 0, 1000]], 'JV-SALE3');

    $this->postJson('/api/admin/accounting-period/closing-entry', [
        'fiscal_year_id' => $this->fiscalYear->id,
    ])->assertSuccessful();

    $response = $this->postJson('/api/admin/accounting-period/closing-entry', [
        'fiscal_year_id' => $this->fiscalYear->id,
    ]);

    $response->assertStatus(422);

    // Only one CLOSING_ENTRY journal exists.
    expect(Journal::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('type', JournalTypeEnum::CLOSING_ENTRY->value)
        ->count())->toBe(1);
});

it('requires a retained-earnings account to be configured', function () {
    // No AccountSetting / no retained earnings configured.
    postJournal($this, [[$this->cashAccount->id, 1000, 0], [$this->salesAccount->id, 0, 1000]], 'JV-SALE4');

    $response = $this->postJson('/api/admin/accounting-period/closing-entry', [
        'fiscal_year_id' => $this->fiscalYear->id,
    ]);

    $response->assertStatus(422);
    expect(Journal::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('type', JournalTypeEnum::CLOSING_ENTRY->value)
        ->exists())->toBeFalse();
});
