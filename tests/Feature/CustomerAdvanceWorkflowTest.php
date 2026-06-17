<?php

use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\AccountSetting;
use App\Models\CustomerAdvance;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Services\Sales\CustomerAdvanceService;
use Illuminate\Validation\ValidationException;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function advWarmCache(): void
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
    advWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2081-82',
        'year_code' => '8182',
        'start_date' => '2024-07-17',
        'end_date' => '2025-07-16',
        'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Advance Test Co',
        'code' => 'ATC-'.uniqid(),
        'inventory_costing_method' => 'fifo',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Advance Tester',
        'email' => 'advance-'.uniqid().'@test.com',
        'password' => bcrypt('secret'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Advance Customer',
        'code' => 'ADV-CUST-'.uniqid(),
        'type' => 'customer',
    ]);

    $this->cashAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Cash',
        'code' => 'CASH-ADV-'.uniqid(),
    ]);

    $this->arAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'AR',
        'code' => 'AR-ADV-'.uniqid(),
    ]);

    $this->advanceAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Customer Advance',
        'code' => 'CADV-'.uniqid(),
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'customer_account_id' => $this->arAccount->id,
        'customer_advance_account_id' => $this->advanceAccount->id,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

function makeAdvanceInvoice(object $test, string $no, float $totalAmount): Invoice
{
    return Invoice::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'party_id' => $test->party->id,
        'invoice_no' => $no,
        'invoice_date' => '2024-10-01',
        'total_amount' => $totalAmount,
        'paid_amount' => 0,
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $test->user->id,
        'approve_user_id' => $test->user->id,
        'approved_at' => now(),
    ]);
}

// ─── create ──────────────────────────────────────────────────────────────────

it('creates a customer advance in draft status', function () {
    $advance = app(CustomerAdvanceService::class)->create([
        'party_id' => $this->party->id,
        'advance_date' => '2024-09-01',
        'amount' => 5000,
        'payment_method' => 'cash',
        'account_id' => $this->cashAccount->id,
    ]);

    expect($advance->status)->toBe(StatusEnum::DRAFT);
    expect((float) $advance->amount)->toBe(5000.0);
    expect((float) $advance->applied_amount)->toBe(0.0);
    expect($advance->advance_no)->toStartWith('ADV-');
});

// ─── approve ─────────────────────────────────────────────────────────────────

it('approves a draft advance and posts a balanced GL journal', function () {
    $advance = CustomerAdvance::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'advance_no' => 'ADV-001',
        'advance_date' => '2024-09-01',
        'amount' => 3000,
        'applied_amount' => 0,
        'payment_method' => 'cash',
        'account_id' => $this->cashAccount->id,
        'status' => StatusEnum::DRAFT,
        'create_user_id' => $this->user->id,
    ]);

    app(CustomerAdvanceService::class)->approve($advance);

    $advance->refresh();
    expect($advance->status)->toBe(StatusEnum::APPROVED);
    expect($advance->approved_at)->not->toBeNull();

    $journal = $advance->journal()->withoutGlobalScopes()->first();
    expect($journal)->not->toBeNull();

    $items = $journal->journalItems()->get();
    expect($items)->toHaveCount(2);

    // DR Cash (advance received)
    $drLine = $items->firstWhere('account_id', $this->cashAccount->id);
    expect($drLine)->not->toBeNull();
    expect((float) $drLine->dr_amount)->toBe(3000.0);
    expect((float) $drLine->cr_amount)->toBe(0.0);

    // CR Customer Advance (liability)
    $crLine = $items->firstWhere('account_id', $this->advanceAccount->id);
    expect($crLine)->not->toBeNull();
    expect((float) $crLine->cr_amount)->toBe(3000.0);

    // Balanced
    expect((float) $items->sum('dr_amount'))->toBe((float) $items->sum('cr_amount'));
});

it('throws when approving a non-draft advance', function () {
    $advance = CustomerAdvance::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'advance_no' => 'ADV-002',
        'advance_date' => '2024-09-01',
        'amount' => 1000,
        'applied_amount' => 0,
        'payment_method' => 'cash',
        'account_id' => $this->cashAccount->id,
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
    ]);

    expect(fn () => app(CustomerAdvanceService::class)->approve($advance))
        ->toThrow(ValidationException::class);
});

// ─── apply ───────────────────────────────────────────────────────────────────

it('applies an advance to an invoice with a balanced GL journal', function () {
    $advance = CustomerAdvance::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'advance_no' => 'ADV-003',
        'advance_date' => '2024-09-01',
        'amount' => 5000,
        'applied_amount' => 0,
        'payment_method' => 'cash',
        'account_id' => $this->cashAccount->id,
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    $invoice = makeAdvanceInvoice($this, 'INV-ADV-001', 4000);

    app(CustomerAdvanceService::class)->apply($advance, $invoice, 2000, '2024-10-15');

    $advance->refresh();
    expect((float) $advance->applied_amount)->toBe(2000.0);
    expect($advance->remainingAmount())->toBe(3000.0);

    // Invoice paid_amount updated via refreshTotals
    expect((float) $invoice->fresh()->paid_amount)->toBe(2000.0);

    // GL journal created
    $journals = \App\Models\Journal::withoutGlobalScopes()
        ->where('reference_type', \App\Models\AdvanceApplication::class)
        ->get();
    expect($journals)->toHaveCount(1);

    $items = $journals->first()->journalItems()->get();
    expect($items)->toHaveCount(2);

    // DR Customer Advance (reduce liability)
    $drLine = $items->firstWhere('account_id', $this->advanceAccount->id);
    expect($drLine)->not->toBeNull();
    expect((float) $drLine->dr_amount)->toBe(2000.0);

    // CR AR (reduce receivable)
    $crLine = $items->firstWhere('account_id', $this->arAccount->id);
    expect($crLine)->not->toBeNull();
    expect((float) $crLine->cr_amount)->toBe(2000.0);

    // Balanced
    expect((float) $items->sum('dr_amount'))->toBe((float) $items->sum('cr_amount'));
});

it('throws when application amount exceeds remaining advance balance', function () {
    $advance = CustomerAdvance::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'advance_no' => 'ADV-004',
        'advance_date' => '2024-09-01',
        'amount' => 1000,
        'applied_amount' => 800,
        'payment_method' => 'cash',
        'account_id' => $this->cashAccount->id,
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    $invoice = makeAdvanceInvoice($this, 'INV-ADV-002', 5000);

    // Remaining = 200, but trying to apply 500
    expect(fn () => app(CustomerAdvanceService::class)->apply($advance, $invoice, 500))
        ->toThrow(ValidationException::class);
});

// ─── void ────────────────────────────────────────────────────────────────────

it('throws when voiding an advance that has applications', function () {
    $advance = CustomerAdvance::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'advance_no' => 'ADV-005',
        'advance_date' => '2024-09-01',
        'amount' => 2000,
        'applied_amount' => 500,
        'payment_method' => 'cash',
        'account_id' => $this->cashAccount->id,
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    $invoice = makeAdvanceInvoice($this, 'INV-ADV-003', 2000);

    \App\Models\AdvanceApplication::create([
        'company_id' => $this->company->id,
        'customer_advance_id' => $advance->id,
        'invoice_id' => $invoice->id,
        'amount' => 500,
        'applied_at' => now()->toDateString(),
        'apply_user_id' => $this->user->id,
    ]);

    expect(fn () => app(CustomerAdvanceService::class)->void($advance))
        ->toThrow(ValidationException::class);
});

it('throws when customer_advance_account_id is not configured', function () {
    AccountSetting::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->update(['customer_advance_account_id' => null]);

    $advance = CustomerAdvance::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'advance_no' => 'ADV-006',
        'advance_date' => '2024-09-01',
        'amount' => 1000,
        'applied_amount' => 0,
        'payment_method' => 'cash',
        'account_id' => $this->cashAccount->id,
        'status' => StatusEnum::DRAFT,
        'create_user_id' => $this->user->id,
    ]);

    expect(fn () => app(CustomerAdvanceService::class)->approve($advance))
        ->toThrow(ValidationException::class);
});
