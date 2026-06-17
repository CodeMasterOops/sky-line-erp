<?php

use App\Models\Bill;
use App\Models\User;
use App\Models\Branch;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Journal;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Models\PosSession;
use App\Enums\UserTypeEnum;
use App\Models\JournalItem;
use App\Models\AccountGroup;
use Laravel\Sanctum\Sanctum;
use App\Models\AccountSetting;
use App\Services\TenantService;
use App\Enums\AccountGroupTypeEnum;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function pr5WarmCache(): void
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
    pr5WarmCache();
    TenantService::setCompanyId(null);
    TenantService::setBranchId(null);

    $this->fiscalYear = FiscalYear::create([
        'year_name' => 'SP5-FY', 'year_code' => 'SP5',
        'start_date' => '2024-07-17', 'end_date' => '2025-07-16', 'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Sprint5 Co',
        'code' => 'SP5-'.uniqid(),
        'inventory_costing_method' => 'fifo',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Test Admin',
        'email' => 'sp5-'.uniqid().'@test.com',
        'password' => bcrypt('secret'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

// ─── Items 1+2: getOpenSession + openTill branch scoping ──────────────────────

it('openTill creates session with branch_id when branch context is set', function () {
    $branch = Branch::create(['company_id' => $this->company->id, 'name' => 'Branch A', 'code' => 'BA']);
    TenantService::setBranchId($branch->id);

    $response = $this->postJson('/api/admin/pos/till/open', ['opening_cash' => 0]);

    $response->assertSuccessful();
    $session = PosSession::withoutGlobalScopes()->where('company_id', $this->company->id)->first();
    expect($session)->not->toBeNull();
    expect($session->branch_id)->toBe($branch->id);
});

it('currentSession returns null when branch context differs from open session', function () {
    $branchA = Branch::create(['company_id' => $this->company->id, 'name' => 'Branch A2', 'code' => 'BA2']);
    $branchB = Branch::create(['company_id' => $this->company->id, 'name' => 'Branch B2', 'code' => 'BB2']);

    PosSession::create([
        'company_id' => $this->company->id,
        'branch_id' => $branchA->id,
        'user_id' => $this->user->id,
        'opening_cash' => 0,
        'status' => 'open',
        'opened_at' => now(),
    ]);

    // Branch B — should NOT see branch A's session
    TenantService::setBranchId($branchB->id);
    $response = $this->getJson('/api/admin/pos/till/current');
    $response->assertSuccessful();
    expect($response->json('data'))->toBeNull();
});

it('currentSession returns the session when branch context matches', function () {
    $branchA = Branch::create(['company_id' => $this->company->id, 'name' => 'Branch A3', 'code' => 'BA3']);

    PosSession::create([
        'company_id' => $this->company->id,
        'branch_id' => $branchA->id,
        'user_id' => $this->user->id,
        'opening_cash' => 0,
        'status' => 'open',
        'opened_at' => now(),
    ]);

    TenantService::setBranchId($branchA->id);
    $response = $this->getJson('/api/admin/pos/till/current');
    $response->assertSuccessful();
    expect($response->json('data'))->not->toBeNull();
});

it('openTill allows simultaneous open sessions on different branches', function () {
    $branchX = Branch::create(['company_id' => $this->company->id, 'name' => 'Branch X', 'code' => 'BX']);
    $branchY = Branch::create(['company_id' => $this->company->id, 'name' => 'Branch Y', 'code' => 'BY']);

    TenantService::setBranchId($branchX->id);
    $this->postJson('/api/admin/pos/till/open', ['opening_cash' => 0])->assertSuccessful();

    TenantService::setBranchId($branchY->id);
    $this->postJson('/api/admin/pos/till/open', ['opening_cash' => 0])->assertSuccessful();

    $count = PosSession::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('status', 'open')
        ->count();
    expect($count)->toBe(2);
});

// ─── Items 3+4: GL journals on openTill / closeTill ───────────────────────────

it('openTill posts a GL journal when POS GL accounts are configured', function () {
    $group = AccountGroup::create([
        'company_id' => $this->company->id,
        'name' => 'Assets-T3', 'code' => 'AST3',
        'account_type' => AccountGroupTypeEnum::Asset,
    ]);
    $cashAcc = Account::create(['company_id' => $this->company->id, 'account_group_id' => $group->id, 'name' => 'POS Cash', 'code' => 'PCASH-'.uniqid()]);
    $floatAcc = Account::create(['company_id' => $this->company->id, 'account_group_id' => $group->id, 'name' => 'Till Float', 'code' => 'FLOAT-'.uniqid()]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'pos_cash_account_id' => $cashAcc->id,
        'pos_float_account_id' => $floatAcc->id,
    ]);

    $response = $this->postJson('/api/admin/pos/till/open', ['opening_cash' => 5000]);
    $response->assertSuccessful();

    $session = PosSession::withoutGlobalScopes()->where('company_id', $this->company->id)->first();
    expect($session->open_journal_id)->not->toBeNull();

    $journal = Journal::withoutGlobalScopes()->find($session->open_journal_id);
    expect($journal)->not->toBeNull();

    $items = JournalItem::where('journal_id', $journal->id)->get();
    $dr = $items->where('account_id', $cashAcc->id)->first();
    $cr = $items->where('account_id', $floatAcc->id)->first();
    expect((float) $dr->dr_amount)->toBe(5000.0);
    expect((float) $cr->cr_amount)->toBe(5000.0);
});

it('closeTill posts float-reversal and over/short journals', function () {
    $group = AccountGroup::create([
        'company_id' => $this->company->id,
        'name' => 'Assets-T4', 'code' => 'AST4',
        'account_type' => AccountGroupTypeEnum::Asset,
    ]);
    $cashAcc = Account::create(['company_id' => $this->company->id, 'account_group_id' => $group->id, 'name' => 'POS Cash T4', 'code' => 'PC4-'.uniqid()]);
    $floatAcc = Account::create(['company_id' => $this->company->id, 'account_group_id' => $group->id, 'name' => 'Till Float T4', 'code' => 'TF4-'.uniqid()]);
    $overShortAcc = Account::create(['company_id' => $this->company->id, 'account_group_id' => $group->id, 'name' => 'Over/Short', 'code' => 'OS-'.uniqid()]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'pos_cash_account_id' => $cashAcc->id,
        'pos_float_account_id' => $floatAcc->id,
        'pos_over_short_account_id' => $overShortAcc->id,
    ]);

    // Open till with Rs 1000
    $this->postJson('/api/admin/pos/till/open', ['opening_cash' => 1000])->assertSuccessful();
    $session = PosSession::withoutGlobalScopes()->where('company_id', $this->company->id)->first();

    // Close with Rs 900 — Rs 100 short; triggers reversal + over/short journal
    $this->postJson('/api/admin/pos/till/close', ['closing_cash' => 900, 'notes' => ''])->assertSuccessful();

    $journals = Journal::withoutGlobalScopes()
        ->whereIn('voucher_no', ['TILL-CLOSE-'.$session->id, 'TILL-DIFF-'.$session->id])
        ->get();
    expect($journals)->toHaveCount(2);
});

it('openTill does not post a GL journal when opening_cash is zero', function () {
    AccountSetting::create(['company_id' => $this->company->id]);

    $this->postJson('/api/admin/pos/till/open', ['opening_cash' => 0])->assertSuccessful();

    $session = PosSession::withoutGlobalScopes()->where('company_id', $this->company->id)->first();
    expect($session->open_journal_id)->toBeNull();
});

// ─── Item 5: COGS uses stock_movement_layers cost ─────────────────────────────

it('todaySummary returns zero COGS when no stock movements exist', function () {
    $response = $this->getJson('/api/admin/pos/today-summary');
    $response->assertSuccessful();
    expect((float) $response->json('data.cogs'))->toBe(0.0);
});

// ─── Item 7: Balance Sheet / P&L filter by account_type enum ─────────────────

it('balanceSheet includes only asset/liability/equity root groups', function () {
    AccountGroup::create([
        'company_id' => $this->company->id,
        'name' => 'TestAssets', 'code' => 'TA7-'.uniqid(),
        'account_type' => AccountGroupTypeEnum::Asset,
    ]);
    AccountGroup::create([
        'company_id' => $this->company->id,
        'name' => 'TestIncome', 'code' => 'TI7-'.uniqid(),
        'account_type' => AccountGroupTypeEnum::Income,
    ]);

    $response = $this->getJson('/api/admin/account-report/balance-sheet?fiscal_year_id='.$this->fiscalYear->id);
    $response->assertSuccessful();

    $names = collect($response->json('data.rows'))->pluck('name')->all();
    expect($names)->toContain('TestAssets');
    expect($names)->not->toContain('TestIncome');
});

it('profitLoss includes only income/expense root groups', function () {
    AccountGroup::create([
        'company_id' => $this->company->id,
        'name' => 'TestRevenue', 'code' => 'TR7-'.uniqid(),
        'account_type' => AccountGroupTypeEnum::Income,
    ]);
    AccountGroup::create([
        'company_id' => $this->company->id,
        'name' => 'TestEquity', 'code' => 'TE7-'.uniqid(),
        'account_type' => AccountGroupTypeEnum::Equity,
    ]);

    $response = $this->getJson('/api/admin/account-report/profit-loss?fiscal_year_id='.$this->fiscalYear->id);
    $response->assertSuccessful();

    $names = collect($response->json('data.rows'))->pluck('name')->all();
    expect($names)->toContain('TestRevenue');
    expect($names)->not->toContain('TestEquity');
});

// ─── Item 9: AR/AP aging excludes future-dated documents ─────────────────────

it('arAging excludes invoices whose invoice_date is after the asOf date', function () {
    Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'invoice_no' => 'FUTURE-INV-SP5',
        'invoice_date' => '2030-01-01',
        'due_date' => '2030-02-01',
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
    ]);

    $response = $this->getJson('/api/admin/account-report/ar-aging?fiscal_year_id='.$this->fiscalYear->id.'&end_date=2024-12-31');
    $response->assertSuccessful();

    $invoiceNos = collect($response->json('data.rows'))->pluck('invoice_no')->all();
    expect($invoiceNos)->not->toContain('FUTURE-INV-SP5');
});

it('apAging excludes bills whose bill_date is after the asOf date', function () {
    Bill::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'bill_no' => 'FUTURE-BILL-SP5',
        'bill_date' => '2030-01-01',
        'due_date' => '2030-02-01',
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
    ]);

    $response = $this->getJson('/api/admin/account-report/ap-aging?fiscal_year_id='.$this->fiscalYear->id.'&end_date=2024-12-31');
    $response->assertSuccessful();

    $billNos = collect($response->json('data.rows'))->pluck('bill_no')->all();
    expect($billNos)->not->toContain('FUTURE-BILL-SP5');
});

// ─── Item 10: vatReturnReconciliation resolves period exactly once ─────────────

it('vatReturnReconciliation returns all expected keys without errors', function () {
    $response = $this->getJson('/api/admin/account-report/vat-return-reconciliation?fiscal_year_id='.$this->fiscalYear->id);
    $response->assertSuccessful();

    $data = $response->json('data');
    expect($data)->toHaveKey('period');
    expect($data)->toHaveKey('output_vat');
    expect($data)->toHaveKey('input_vat');
    expect($data)->toHaveKey('computed_net_vat');
    expect($data)->toHaveKey('reconciled');
});

// ─── Item 11: BooksHealthService::invalidateCache at posting chokepoints ───────

it('BooksHealthService::invalidateCache removes the company health cache key', function () {
    $companyId = $this->company->id;
    Cache::put("books_health_{$companyId}", ['status' => 'healthy'], 300);

    app(\App\Services\Accounting\BooksHealthService::class)->invalidateCache($companyId);

    expect(Cache::has("books_health_{$companyId}"))->toBeFalse();
});

it('openTill invalidates the books health cache when accounts are configured', function () {
    $group = AccountGroup::create([
        'company_id' => $this->company->id,
        'name' => 'Assets-T11', 'code' => 'AST11',
        'account_type' => AccountGroupTypeEnum::Asset,
    ]);
    $cashAcc = Account::create(['company_id' => $this->company->id, 'account_group_id' => $group->id, 'name' => 'POS Cash T11', 'code' => 'PCH11-'.uniqid()]);
    $floatAcc = Account::create(['company_id' => $this->company->id, 'account_group_id' => $group->id, 'name' => 'Float T11', 'code' => 'FLT11-'.uniqid()]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'pos_cash_account_id' => $cashAcc->id,
        'pos_float_account_id' => $floatAcc->id,
    ]);

    $cacheKey = "books_health_{$this->company->id}";
    Cache::put($cacheKey, ['status' => 'healthy'], 300);

    $this->postJson('/api/admin/pos/till/open', ['opening_cash' => 100])->assertSuccessful();

    expect(Cache::has($cacheKey))->toBeFalse();
});

// ─── Item 12: Cash flow report declares accrual basis ─────────────────────────

it('cashFlow response includes accrual basis label and note', function () {
    $response = $this->getJson('/api/admin/account-report/cash-flow?fiscal_year_id='.$this->fiscalYear->id);
    $response->assertSuccessful();

    expect($response->json('data.basis'))->toBe('accrual');
    expect($response->json('data.basis_note'))->toContain('accrual');
});
