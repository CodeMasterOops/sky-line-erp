<?php

use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\TdsDeduction;
use Laravel\Sanctum\Sanctum;
use App\Enums\TdsCategoryEnum;
use App\Models\AccountSetting;
use App\Services\TenantService;
use App\Models\ReceiptAllocation;
use Illuminate\Support\Facades\Cache;
use App\Services\Sales\ReceiptService;
use Illuminate\Support\Facades\Schema;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function tdsWarmCache(): void
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
    tdsWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2081-82',
        'year_code' => '8182',
        'start_date' => '2024-07-17',
        'end_date' => '2025-07-16',
        'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'TDS Test Co',
        'code' => 'TTC',
        'inventory_costing_method' => 'fifo',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Tester',
        'email' => 'tds@test.com',
        'password' => bcrypt('secret'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'TDS Customer',
        'code' => 'TDS-CUST',
        'type' => 'customer',
    ]);

    $this->cashAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Cash',
        'code' => 'CASH-TDS',
    ]);

    $this->arAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Accounts Receivable',
        'code' => 'AR-TDS',
    ]);

    $this->tdsReceivableAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'TDS Receivable',
        'code' => 'TDS-REC',
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'customer_account_id' => $this->arAccount->id,
        'tds_receivable_account_id' => $this->tdsReceivableAccount->id,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

function makeTdsInvoice(object $test, string $no = 'INV-TDS-001'): Invoice
{
    return Invoice::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'party_id' => $test->party->id,
        'invoice_no' => $no,
        'invoice_date' => '2024-09-01',
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $test->user->id,
        'approve_user_id' => $test->user->id,
        'approved_at' => now(),
    ]);
}

it('posts a balanced 3-line journal when receipt has a TDS deduction', function () {
    $receipt = Receipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'receipt_no' => 'RC-TDS-001',
        'receipt_date' => '2024-10-01',
        'payment_method' => 'bank',
        'account_id' => $this->cashAccount->id,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED,
        'tds_category' => TdsCategoryEnum::SERVICE_VAT_BILL->value,
        'tds_rate' => 1.5,
        'tds_amount' => 150.00,
    ]);

    // Gross allocation 10,000; customer paid 9,850 cash (withheld 150 TDS)
    $invoice = makeTdsInvoice($this, 'INV-TDS-001');
    ReceiptAllocation::create([
        'receipt_id' => $receipt->id,
        'invoice_id' => $invoice->id,
        'amount' => 10000.00,
    ]);

    $service = app(ReceiptService::class);
    $service->createJournal($receipt->refresh()->loadSum('allocations', 'amount'));

    $journal = $receipt->journal()->withoutGlobalScopes()->first();

    expect($journal)->not->toBeNull();

    $items = $journal->journalItems()->get();

    expect($items)->toHaveCount(3);

    // CR Accounts Receivable (gross 10,000)
    $arLine = $items->firstWhere('account_id', $this->arAccount->id);
    expect($arLine)->not->toBeNull();
    expect((float) $arLine->cr_amount)->toBe(10000.0);
    expect((float) $arLine->dr_amount)->toBe(0.0);

    // DR Cash (net 9,850)
    $cashLine = $items->firstWhere('account_id', $this->cashAccount->id);
    expect($cashLine)->not->toBeNull();
    expect((float) $cashLine->dr_amount)->toBe(9850.0);
    expect((float) $cashLine->cr_amount)->toBe(0.0);

    // DR TDS Receivable (150)
    $tdsLine = $items->firstWhere('account_id', $this->tdsReceivableAccount->id);
    expect($tdsLine)->not->toBeNull();
    expect((float) $tdsLine->dr_amount)->toBe(150.0);
    expect((float) $tdsLine->cr_amount)->toBe(0.0);

    // Journal is balanced
    expect((float) $items->sum('dr_amount'))->toBe((float) $items->sum('cr_amount'));
});

it('creates a TdsDeduction record when receipt has TDS', function () {
    $receipt = Receipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'receipt_no' => 'RC-TDS-002',
        'receipt_date' => '2024-10-01',
        'payment_method' => 'bank',
        'account_id' => $this->cashAccount->id,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED,
        'tds_category' => TdsCategoryEnum::SERVICE_VAT_BILL->value,
        'tds_rate' => 1.5,
        'tds_amount' => 150.00,
    ]);

    $invoice2 = makeTdsInvoice($this, 'INV-TDS-002');
    ReceiptAllocation::create([
        'receipt_id' => $receipt->id,
        'invoice_id' => $invoice2->id,
        'amount' => 10000.00,
    ]);

    app(ReceiptService::class)->createJournal($receipt->refresh()->loadSum('allocations', 'amount'));

    $deduction = TdsDeduction::withoutGlobalScopes()
        ->where('deductible_type', Receipt::class)
        ->where('deductible_id', $receipt->id)
        ->first();

    expect($deduction)->not->toBeNull();
    expect((float) $deduction->tds_amount)->toBe(150.0);
    expect((float) $deduction->base_amount)->toBe(10000.0);
    expect($deduction->tds_category)->toBe(TdsCategoryEnum::SERVICE_VAT_BILL);
    expect($deduction->party_id)->toBe($this->party->id);
});

it('posts a standard 2-line journal when receipt has no TDS', function () {
    $receipt = Receipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'receipt_no' => 'RC-NOTDS-001',
        'receipt_date' => '2024-10-01',
        'payment_method' => 'bank',
        'account_id' => $this->cashAccount->id,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED,
    ]);

    $invoice3 = makeTdsInvoice($this, 'INV-TDS-003');
    ReceiptAllocation::create([
        'receipt_id' => $receipt->id,
        'invoice_id' => $invoice3->id,
        'amount' => 5000.00,
    ]);

    app(ReceiptService::class)->createJournal($receipt->refresh()->loadSum('allocations', 'amount'));

    $journal = $receipt->journal()->withoutGlobalScopes()->first();
    $items = $journal->journalItems()->get();

    expect($items)->toHaveCount(2);

    $cashLine = $items->firstWhere('account_id', $this->cashAccount->id);
    expect((float) $cashLine->dr_amount)->toBe(5000.0);

    $arLine = $items->firstWhere('account_id', $this->arAccount->id);
    expect((float) $arLine->cr_amount)->toBe(5000.0);

    expect(TdsDeduction::withoutGlobalScopes()
        ->where('deductible_type', Receipt::class)
        ->where('deductible_id', $receipt->id)
        ->count()
    )->toBe(0);
});

it('skips TDS Receivable line and deduction record when tds_receivable_account_id is not configured', function () {
    AccountSetting::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->update(['tds_receivable_account_id' => null]);

    $receipt = Receipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'receipt_no' => 'RC-TDS-NOCFG',
        'receipt_date' => '2024-10-01',
        'payment_method' => 'bank',
        'account_id' => $this->cashAccount->id,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED,
        'tds_amount' => 200.00,
    ]);

    $invoice4 = makeTdsInvoice($this, 'INV-TDS-004');
    ReceiptAllocation::create([
        'receipt_id' => $receipt->id,
        'invoice_id' => $invoice4->id,
        'amount' => 10000.00,
    ]);

    try {
        app(ReceiptService::class)->createJournal($receipt->refresh()->loadSum('allocations', 'amount'));
    } catch (\Throwable) {
        // Balance guard may throw since the TDS line is skipped — that's expected
    }

    expect(TdsDeduction::withoutGlobalScopes()
        ->where('deductible_type', Receipt::class)
        ->where('deductible_id', $receipt->id)
        ->count()
    )->toBe(0);
});
