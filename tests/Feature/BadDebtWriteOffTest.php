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
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use App\Services\Sales\BadDebtService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function badDebtWarmCache(): void
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
    badDebtWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2081-82',
        'year_code' => '8182',
        'start_date' => '2024-07-17',
        'end_date' => '2025-07-16',
        'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Bad Debt Test Co',
        'code' => 'BDC-'.uniqid(),
        'inventory_costing_method' => 'fifo',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Bad Debt Tester',
        'email' => 'bd-'.uniqid().'@test.com',
        'password' => bcrypt('secret'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Defaulting Customer',
        'code' => 'DFLT-'.uniqid(),
        'type' => 'customer',
    ]);

    $this->arAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'AR',
        'code' => 'AR-BD-'.uniqid(),
    ]);

    $this->badDebtAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Bad Debt Expense',
        'code' => 'BDE-'.uniqid(),
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'customer_account_id' => $this->arAccount->id,
        'bad_debt_account_id' => $this->badDebtAccount->id,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

function makeBdInvoice(object $test, string $no, float $total, float $paid = 0.0): Invoice
{
    return Invoice::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'party_id' => $test->party->id,
        'invoice_no' => $no,
        'invoice_date' => '2024-08-01',
        'total_amount' => $total,
        'paid_amount' => $paid,
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $test->user->id,
        'approve_user_id' => $test->user->id,
        'approved_at' => now(),
    ]);
}

// ─── happy path ───────────────────────────────────────────────────────────────

it('writes off the full outstanding balance with a balanced GL journal', function () {
    $invoice = makeBdInvoice($this, 'INV-BD-001', 10000, 3000);

    app(BadDebtService::class)->writeOff($invoice, 7000, 'Customer insolvent', '2024-12-01');

    $invoice->refresh();
    expect($invoice->written_off_at)->not->toBeNull();
    expect((float) $invoice->write_off_amount)->toBe(7000.0);
    expect($invoice->write_off_remarks)->toBe('Customer insolvent');

    $journal = \App\Models\Journal::withoutGlobalScopes()
        ->where('reference_id', $invoice->id)
        ->first();
    expect($journal)->not->toBeNull();
    expect($journal->voucher_no)->toStartWith('BDWO-');

    $items = $journal->journalItems()->get();
    expect($items)->toHaveCount(2);

    // DR Bad Debt Expense
    $drLine = $items->firstWhere('account_id', $this->badDebtAccount->id);
    expect($drLine)->not->toBeNull();
    expect((float) $drLine->dr_amount)->toBe(7000.0);
    expect((float) $drLine->cr_amount)->toBe(0.0);

    // CR Accounts Receivable
    $crLine = $items->firstWhere('account_id', $this->arAccount->id);
    expect($crLine)->not->toBeNull();
    expect((float) $crLine->cr_amount)->toBe(7000.0);
    expect((float) $crLine->dr_amount)->toBe(0.0);

    // Balanced
    expect((float) $items->sum('dr_amount'))->toBe((float) $items->sum('cr_amount'));
});

it('writes off a partial amount when invoice is partially paid', function () {
    $invoice = makeBdInvoice($this, 'INV-BD-002', 5000, 2000);

    app(BadDebtService::class)->writeOff($invoice, 3000, 'Partial recovery only');

    expect((float) $invoice->fresh()->write_off_amount)->toBe(3000.0);
});

// ─── guard rails ─────────────────────────────────────────────────────────────

it('throws when writing off a voided invoice', function () {
    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'invoice_no' => 'INV-BD-VOID-001',
        'invoice_date' => '2024-08-01',
        'total_amount' => 5000,
        'paid_amount' => 0,
        'status' => StatusEnum::APPROVED,
        'voided_at' => now(),
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    expect(fn () => app(BadDebtService::class)->writeOff($invoice, 5000))
        ->toThrow(ValidationException::class);
});

it('throws when writing off an already written-off invoice', function () {
    $invoice = makeBdInvoice($this, 'INV-BD-003', 5000, 0);
    app(BadDebtService::class)->writeOff($invoice, 5000);

    expect(fn () => app(BadDebtService::class)->writeOff($invoice->fresh(), 5000))
        ->toThrow(ValidationException::class);
});

it('throws when write-off amount exceeds due amount', function () {
    $invoice = makeBdInvoice($this, 'INV-BD-004', 2000, 1000);

    // Due = 2000 - 1000 = 1000, trying to write off 1500
    expect(fn () => app(BadDebtService::class)->writeOff($invoice, 1500))
        ->toThrow(ValidationException::class);
});

it('throws when bad_debt_account_id is not configured', function () {
    AccountSetting::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->update(['bad_debt_account_id' => null]);

    $invoice = makeBdInvoice($this, 'INV-BD-005', 5000, 0);

    expect(fn () => app(BadDebtService::class)->writeOff($invoice, 5000))
        ->toThrow(ValidationException::class);
});
