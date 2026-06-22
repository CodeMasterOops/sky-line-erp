<?php

use App\Models\User;
use App\Models\Account;
use App\Models\Company;
use App\Models\Journal;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\BankAccount;
use App\Models\JournalItem;
use Laravel\Sanctum\Sanctum;
use App\Enums\JournalTypeEnum;
use App\Models\AccountSetting;
use App\Services\TenantService;
use App\Models\BankMatchingRule;
use App\Models\BankStatementLine;
use App\Models\BankReconciliation;
use Illuminate\Support\Facades\Cache;
use App\Models\ReconciliationAuditLog;
use Illuminate\Support\Facades\Schema;
use App\Services\Accounting\BankReconciliationService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function bankReconWarmAllTablesCache(): void
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
    bankReconWarmAllTablesCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Recon Co', 'code' => 'RCN',
        'inventory_costing_method' => 'fifo',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Banker', 'email' => 'recon-'.uniqid().'@example.com',
        'password' => bcrypt('password'), 'user_type' => UserTypeEnum::ADMIN,
    ]);

    TenantService::setCompanyId($this->company->id);

    $this->bankGl = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'Bank GL', 'code' => 'BANK-RCN',
    ]);
    $this->arGl = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'AR', 'code' => 'AR-RCN',
    ]);
    $this->chargesGl = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'Bank Charges', 'code' => 'BCHG-RCN',
    ]);
    $this->suspenseGl = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'Suspense', 'code' => 'SUSP-RCN',
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'suspense_account_id' => $this->suspenseGl->id,
    ]);

    $this->bankAccount = BankAccount::create([
        'company_id' => $this->company->id,
        'account_id' => $this->bankGl->id,
        'bank_name' => 'NMB Bank', 'account_number' => '123',
        'currency' => 'NPR', 'is_active' => true, 'opening_balance' => 0,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
});

/**
 * Post an approved bank journal: money-in is Dr bank / Cr contra.
 */
function reconBankJournal(int $companyId, int $fiscalYearId, int $bankAccountId, int $contraId, float $signed, string $date): JournalItem
{
    $journal = Journal::create([
        'company_id' => $companyId, 'fiscal_year_id' => $fiscalYearId,
        'type' => JournalTypeEnum::RECEIPT->value, 'voucher_no' => 'RC-'.uniqid(),
        'date' => $date, 'status' => StatusEnum::APPROVED->value, 'approved_at' => now(),
        'create_user_id' => auth('admin')->id(), 'approve_user_id' => auth('admin')->id(),
    ]);
    $amount = round(abs($signed), 2);
    $bankItem = $journal->journalItems()->create([
        'account_id' => $bankAccountId,
        'dr_amount' => $signed >= 0 ? $amount : 0,
        'cr_amount' => $signed >= 0 ? 0 : $amount,
    ]);
    $journal->journalItems()->create([
        'account_id' => $contraId,
        'dr_amount' => $signed >= 0 ? 0 : $amount,
        'cr_amount' => $signed >= 0 ? $amount : 0,
    ]);

    return $bankItem;
}

it('computes a debit-normal GL balance for the bank account', function () {
    reconBankJournal($this->company->id, $this->fiscalYear->id, $this->bankGl->id, $this->arGl->id, 1000, '2026-03-01');
    reconBankJournal($this->company->id, $this->fiscalYear->id, $this->bankGl->id, $this->chargesGl->id, -200, '2026-03-02');

    expect(app(BankReconciliationService::class)->glBalance($this->bankAccount))->toBe(800.0);
});

it('auto-matches a statement line to its journal item by amount and date', function () {
    reconBankJournal($this->company->id, $this->fiscalYear->id, $this->bankGl->id, $this->arGl->id, 1000, '2026-03-01');

    BankStatementLine::create([
        'bank_account_id' => $this->bankAccount->id, 'transaction_date' => '2026-03-01',
        'description' => 'Customer deposit', 'debit' => 0, 'credit' => 1000, 'status' => 'unmatched',
        'hash' => 'h1',
    ]);

    $this->postJson("/api/admin/bank-reconciliation/bank-accounts/{$this->bankAccount->id}/auto-match")
        ->assertOk()->assertJsonPath('matched', 1);

    expect(BankStatementLine::where('bank_account_id', $this->bankAccount->id)->first())
        ->status->toBe('matched')
        ->match_type->toBe('auto');
});

it('does not auto-match when amount is outside tolerance', function () {
    reconBankJournal($this->company->id, $this->fiscalYear->id, $this->bankGl->id, $this->arGl->id, 999, '2026-03-01');

    BankStatementLine::create([
        'bank_account_id' => $this->bankAccount->id, 'transaction_date' => '2026-03-01',
        'debit' => 0, 'credit' => 1000, 'status' => 'unmatched', 'hash' => 'h2',
    ]);

    $this->postJson("/api/admin/bank-reconciliation/bank-accounts/{$this->bankAccount->id}/auto-match")
        ->assertOk()->assertJsonPath('matched', 0);
});

it('creates a balanced journal on create-on-match for a bank charge', function () {
    $line = BankStatementLine::create([
        'bank_account_id' => $this->bankAccount->id, 'transaction_date' => '2026-03-05',
        'description' => 'Service charge', 'debit' => 50, 'credit' => 0, 'status' => 'unmatched', 'hash' => 'h3',
    ]);

    $this->postJson("/api/admin/bank-reconciliation/statement-lines/{$line->id}/create-entry", [
        'contra_account_id' => $this->chargesGl->id,
    ])->assertCreated();

    $line->refresh();
    expect($line->status)->toBe('matched')->and($line->match_type)->toBe('created');

    $journal = Journal::where('company_id', $this->company->id)->latest('id')->with('journalItems')->first();
    expect(round($journal->journalItems->sum('dr_amount'), 2))->toBe(round($journal->journalItems->sum('cr_amount'), 2))
        ->and((float) $journal->journalItems->sum('dr_amount'))->toBe(50.0);

    // Money out ⇒ expense debited, bank credited.
    $chargeLeg = $journal->journalItems->firstWhere('account_id', $this->chargesGl->id);
    expect((float) $chargeLeg->dr_amount)->toBe(50.0);
});

it('parks an unexplained line to the suspense account', function () {
    $line = BankStatementLine::create([
        'bank_account_id' => $this->bankAccount->id, 'transaction_date' => '2026-03-06',
        'description' => 'Unknown credit', 'debit' => 0, 'credit' => 300, 'status' => 'unmatched', 'hash' => 'h4',
    ]);

    $this->postJson("/api/admin/bank-reconciliation/statement-lines/{$line->id}/park-suspense")
        ->assertCreated();

    $journal = Journal::where('company_id', $this->company->id)->latest('id')->with('journalItems')->first();
    expect($journal->journalItems->pluck('account_id'))->toContain($this->suspenseGl->id);
});

it('applies a matching rule to post bank-only lines', function () {
    BankMatchingRule::create([
        'company_id' => $this->company->id, 'bank_account_id' => null, 'priority' => 10,
        'match_field' => 'description', 'operator' => 'contains', 'pattern' => 'SERVICE CHARGE',
        'target_account_id' => $this->chargesGl->id, 'set_status' => 'matched', 'is_active' => true,
    ]);

    BankStatementLine::create([
        'bank_account_id' => $this->bankAccount->id, 'transaction_date' => '2026-03-07',
        'description' => 'Monthly service charge', 'debit' => 25, 'credit' => 0, 'status' => 'unmatched', 'hash' => 'h5',
    ]);

    $this->postJson("/api/admin/bank-reconciliation/bank-accounts/{$this->bankAccount->id}/apply-rules")
        ->assertOk()->assertJsonPath('posted', 1);

    expect(BankStatementLine::where('bank_account_id', $this->bankAccount->id)->first())
        ->match_type->toBe('rule');
});

it('runs a reconciliation session: start, complete, lock, and write back', function () {
    reconBankJournal($this->company->id, $this->fiscalYear->id, $this->bankGl->id, $this->arGl->id, 1000, '2026-03-01');

    $line = BankStatementLine::create([
        'bank_account_id' => $this->bankAccount->id, 'transaction_date' => '2026-03-01',
        'debit' => 0, 'credit' => 1000, 'status' => 'unmatched', 'hash' => 'h6',
    ]);

    app(BankReconciliationService::class)->autoMatch($this->bankAccount);

    $start = $this->postJson("/api/admin/bank-reconciliation/bank-accounts/{$this->bankAccount->id}/reconciliations", [
        'period_start' => '2026-03-01', 'period_end' => '2026-03-31',
        'statement_closing_balance' => 1000,
    ])->assertCreated();

    $reconId = $start->json('data.id');

    $this->postJson("/api/admin/bank-reconciliation/reconciliations/{$reconId}/complete")
        ->assertOk()->assertJsonPath('data.status', 'locked');

    $recon = BankReconciliation::find($reconId);
    expect($recon->difference)->toBe(0.0)
        ->and($recon->reconciled_by)->toBe($this->user->id)
        ->and($line->fresh()->reconciliation_id)->toBe($reconId);

    expect($this->bankAccount->fresh()->last_reconciled_balance)->toBe(1000.0);
});

it('refuses to complete a reconciliation with a non-zero difference', function () {
    $this->postJson("/api/admin/bank-reconciliation/bank-accounts/{$this->bankAccount->id}/reconciliations", [
        'period_start' => '2026-03-01', 'period_end' => '2026-03-31',
        'statement_closing_balance' => 5000,
    ])->assertCreated();

    $reconId = BankReconciliation::where('bank_account_id', $this->bankAccount->id)->value('id');

    $this->postJson("/api/admin/bank-reconciliation/reconciliations/{$reconId}/complete")
        ->assertStatus(422);
});

it('writes an audit log row for every match action', function () {
    reconBankJournal($this->company->id, $this->fiscalYear->id, $this->bankGl->id, $this->arGl->id, 1000, '2026-03-01');
    BankStatementLine::create([
        'bank_account_id' => $this->bankAccount->id, 'transaction_date' => '2026-03-01',
        'debit' => 0, 'credit' => 1000, 'status' => 'unmatched', 'hash' => 'h7',
    ]);

    app(BankReconciliationService::class)->autoMatch($this->bankAccount);

    expect(ReconciliationAuditLog::where('company_id', $this->company->id)->where('action', 'matched')->count())->toBe(1);
});

it('rejects matching a line that belongs to another company (scoping)', function () {
    $otherCompany = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id, 'company_name' => 'Other', 'code' => 'OTH',
        'inventory_costing_method' => 'fifo',
    ]);
    $otherGl = Account::withoutGlobalScopes()->create([
        'company_id' => $otherCompany->id, 'account_group_id' => null, 'name' => 'Other Bank', 'code' => 'OBANK',
    ]);
    $otherBank = BankAccount::withoutGlobalScopes()->create([
        'company_id' => $otherCompany->id, 'account_id' => $otherGl->id,
        'bank_name' => 'Other', 'account_number' => '9', 'is_active' => true,
    ]);
    $foreignLine = BankStatementLine::create([
        'bank_account_id' => $otherBank->id, 'transaction_date' => '2026-03-01',
        'debit' => 0, 'credit' => 10, 'status' => 'unmatched', 'hash' => 'hx',
    ]);

    $this->postJson("/api/admin/bank-reconciliation/statement-lines/{$foreignLine->id}/park-suspense")
        ->assertForbidden();
});
