<?php

use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\Product;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\InvoiceItem;
use App\Models\JournalItem;
use App\Enums\PartyTypeEnum;
use App\Models\AccountGroup;
use Laravel\Sanctum\Sanctum;
use App\Enums\ProductTypeEnum;
use App\Models\AccountSetting;
use App\Models\AdvanceReceipt;
use App\Models\ProductVariant;
use App\Services\TenantService;
use App\Enums\PaymentMethodEnum;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function advWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

function advSetupAccounts(object $test): void
{
    $group = AccountGroup::create([
        'company_id' => $test->company->id,
        'name' => 'Adv GL '.uniqid(),
        'code' => 'ADVGL-'.uniqid(),
    ]);

    $test->arAccount = Account::create([
        'company_id' => $test->company->id,
        'account_group_id' => $group->id,
        'name' => 'AR Adv',
        'code' => 'AR-ADV-'.uniqid(),
        'is_active' => true,
    ]);

    $test->cashAccount = Account::create([
        'company_id' => $test->company->id,
        'account_group_id' => $group->id,
        'name' => 'Cash Adv',
        'code' => 'CASH-ADV-'.uniqid(),
        'is_active' => true,
    ]);

    $test->advanceLiabilityAccount = Account::create([
        'company_id' => $test->company->id,
        'account_group_id' => $group->id,
        'name' => 'Advance from Customers',
        'code' => 'ADV-LIAB-'.uniqid(),
        'is_active' => true,
    ]);

    AccountSetting::create([
        'company_id' => $test->company->id,
        'customer_account_id' => $test->arAccount->id,
        'sales_account_id' => $test->arAccount->id,
        'cash_sales_account_id' => $test->arAccount->id,
        'bank_sales_account_id' => $test->arAccount->id,
        'advance_from_customers_account_id' => $test->advanceLiabilityAccount->id,
    ]);
}

function advCreateApprovedInvoice(object $test, float $amount = 5000.0): Invoice
{
    $product = Product::create([
        'company_id' => $test->company->id,
        'name' => 'AdvSvc-'.uniqid(),
        'code' => 'ADVSVC-'.uniqid(),
        'product_type' => ProductTypeEnum::SERVICE,
    ]);

    $variant = ProductVariant::create([
        'company_id' => $test->company->id,
        'product_id' => $product->id,
        'sku' => 'ADVSV-'.uniqid(),
        'sales_price' => $amount,
        'is_default' => true,
    ]);

    $invoice = Invoice::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'party_id' => $test->party->id,
        'invoice_no' => 'INV-ADV-'.uniqid(),
        'invoice_date' => now()->toDateString(),
        'create_user_id' => $test->user->id,
        'approve_user_id' => $test->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_variant_id' => $variant->id,
        'quantity' => 1,
        'rate' => $amount,
        'tax_amount' => 0,
        'discount_amount' => 0,
    ]);

    return $invoice;
}

beforeEach(function () {
    advWarmCache();
    TenantService::setCompanyId(null);
    TenantService::setBranchId(null);

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026ADV',
        'year_code' => '26ADV',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Advance Test Co',
        'code' => 'ADVC-'.uniqid(),
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Adv Tester',
        'email' => 'adv-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Adv Customer',
        'code' => 'ADVCUST-'.uniqid(),
        'type' => PartyTypeEnum::CUSTOMER,
    ]);

    TenantService::setCompanyId($this->company->id);
    Sanctum::actingAs($this->user, ['*'], 'admin');
});

// ─── CRUD ────────────────────────────────────────────────────────────────────

it('creates an advance receipt in draft status', function () {
    advSetupAccounts($this);

    $response = $this->postJson('/api/admin/advance-receipt', [
        'advance_date' => '2026-06-01',
        'party_id' => $this->party->id,
        'payment_method' => 'cash',
        'account_id' => $this->cashAccount->id,
        'amount' => 2000,
        'remarks' => 'Advance for project',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.status', StatusEnum::DRAFT->value);
    expect((float) $response->json('data.amount'))->toBe(2000.0);
    expect((float) $response->json('data.balance'))->toBe(2000.0);

    $this->assertDatabaseHas('advance_receipts', [
        'party_id' => $this->party->id,
        'amount' => 2000,
        'status' => StatusEnum::DRAFT->value,
    ]);
});

it('generates advance_no automatically', function () {
    advSetupAccounts($this);

    $response = $this->postJson('/api/admin/advance-receipt', [
        'advance_date' => '2026-06-01',
        'party_id' => $this->party->id,
        'payment_method' => 'cash',
        'account_id' => $this->cashAccount->id,
        'amount' => 500,
    ]);

    $response->assertCreated();
    $advanceNo = $response->json('data.advance_no');
    expect($advanceNo)->toStartWith('ADV-');
});

it('returns advance by id with correct fields', function () {
    advSetupAccounts($this);

    $advance = AdvanceReceipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'advance_no' => 'ADV-TEST-1',
        'advance_date' => '2026-06-01',
        'payment_method' => PaymentMethodEnum::Cash->value,
        'account_id' => $this->cashAccount->id,
        'amount' => 3000,
        'adjusted_amount' => 0,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT->value,
    ]);

    $response = $this->getJson("/api/admin/advance-receipt/{$advance->id}");

    $response->assertOk();
    $response->assertJsonPath('data.advance_no', 'ADV-TEST-1');
    expect((float) $response->json('data.balance'))->toBe(3000.0);
    expect((float) $response->json('data.adjusted_amount'))->toBe(0.0);
});

it('updates a draft advance receipt', function () {
    advSetupAccounts($this);

    $advance = AdvanceReceipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'advance_no' => 'ADV-UPDT-1',
        'advance_date' => '2026-06-01',
        'payment_method' => PaymentMethodEnum::Cash->value,
        'account_id' => $this->cashAccount->id,
        'amount' => 1000,
        'adjusted_amount' => 0,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT->value,
    ]);

    $response = $this->putJson("/api/admin/advance-receipt/{$advance->id}", [
        'advance_date' => '2026-06-05',
        'party_id' => $this->party->id,
        'payment_method' => 'cash',
        'account_id' => $this->cashAccount->id,
        'amount' => 1500,
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('advance_receipts', [
        'id' => $advance->id,
        'amount' => 1500,
    ]);
});

it('deletes a draft advance receipt', function () {
    advSetupAccounts($this);

    $advance = AdvanceReceipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'advance_no' => 'ADV-DEL-1',
        'advance_date' => '2026-06-01',
        'payment_method' => PaymentMethodEnum::Cash->value,
        'account_id' => $this->cashAccount->id,
        'amount' => 500,
        'adjusted_amount' => 0,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT->value,
    ]);

    $response = $this->deleteJson("/api/admin/advance-receipt/{$advance->id}");

    $response->assertOk();
    $this->assertSoftDeleted('advance_receipts', ['id' => $advance->id]);
});

it('rejects deleting an approved advance receipt', function () {
    advSetupAccounts($this);

    $advance = AdvanceReceipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'advance_no' => 'ADV-NODL-1',
        'advance_date' => '2026-06-01',
        'payment_method' => PaymentMethodEnum::Cash->value,
        'account_id' => $this->cashAccount->id,
        'amount' => 500,
        'adjusted_amount' => 0,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED->value,
    ]);

    $response = $this->deleteJson("/api/admin/advance-receipt/{$advance->id}");

    $response->assertUnprocessable();
});

// ─── Approval & GL ───────────────────────────────────────────────────────────

it('approves an advance receipt and posts GL journal (DR Cash, CR Advance Liability)', function () {
    advSetupAccounts($this);

    $advance = AdvanceReceipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'advance_no' => 'ADV-APPR-1',
        'advance_date' => '2026-06-01',
        'payment_method' => PaymentMethodEnum::Cash->value,
        'account_id' => $this->cashAccount->id,
        'amount' => 2000,
        'adjusted_amount' => 0,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT->value,
    ]);

    $response = $this->postJson("/api/admin/advance-receipt/{$advance->id}/approve");

    $response->assertOk();
    $response->assertJsonPath('data.status', StatusEnum::APPROVED->value);

    // Verify journal was created
    $journal = Journal::withoutGlobalScopes()
        ->where('reference_type', AdvanceReceipt::class)
        ->where('reference_id', $advance->id)
        ->first();

    expect($journal)->not->toBeNull();

    $items = JournalItem::withoutGlobalScopes()
        ->where('journal_id', $journal->id)
        ->get();

    $drTotal = $items->sum('dr_amount');
    $crTotal = $items->sum('cr_amount');

    expect(round((float) $drTotal, 2))->toBe(2000.0);
    expect(round((float) $crTotal, 2))->toBe(2000.0);

    // DR Cash
    $drLine = $items->firstWhere('account_id', $this->cashAccount->id);
    expect((float) $drLine->dr_amount)->toBe(2000.0);

    // CR Advance from Customers (liability)
    $crLine = $items->firstWhere('account_id', $this->advanceLiabilityAccount->id);
    expect((float) $crLine->cr_amount)->toBe(2000.0);
});

it('rejects approving an already approved advance', function () {
    advSetupAccounts($this);

    $advance = AdvanceReceipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'advance_no' => 'ADV-DBLAP-1',
        'advance_date' => '2026-06-01',
        'payment_method' => PaymentMethodEnum::Cash->value,
        'account_id' => $this->cashAccount->id,
        'amount' => 500,
        'adjusted_amount' => 0,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED->value,
    ]);

    // Returns 200 idempotently with "already approved" message
    $response = $this->postJson("/api/admin/advance-receipt/{$advance->id}/approve");
    $response->assertOk();
    $response->assertJsonPath('message', 'Advance Receipt Already Approved');
});

// ─── Adjustment ──────────────────────────────────────────────────────────────

it('adjusts advance against an invoice and updates adjusted_amount and balance', function () {
    advSetupAccounts($this);
    $invoice = advCreateApprovedInvoice($this, 5000.0);

    $advance = AdvanceReceipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'advance_no' => 'ADV-ADJ-1',
        'advance_date' => '2026-06-01',
        'payment_method' => PaymentMethodEnum::Cash->value,
        'account_id' => $this->cashAccount->id,
        'amount' => 3000,
        'adjusted_amount' => 0,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED->value,
    ]);

    $response = $this->postJson("/api/admin/advance-receipt/{$advance->id}/adjust", [
        'invoice_id' => $invoice->id,
        'amount' => 1000,
    ]);

    $response->assertOk();
    expect((float) $response->json('data.adjusted_amount'))->toBe(1000.0);
    expect((float) $response->json('data.balance'))->toBe(2000.0);

    $this->assertDatabaseHas('advance_adjustments', [
        'advance_receipt_id' => $advance->id,
        'invoice_id' => $invoice->id,
        'amount' => 1000,
    ]);
});

it('posts adjustment GL (DR Advance Liability, CR AR) when adjusting advance', function () {
    advSetupAccounts($this);
    $invoice = advCreateApprovedInvoice($this, 5000.0);

    $advance = AdvanceReceipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'advance_no' => 'ADV-ADJGL-1',
        'advance_date' => '2026-06-01',
        'payment_method' => PaymentMethodEnum::Cash->value,
        'account_id' => $this->cashAccount->id,
        'amount' => 2000,
        'adjusted_amount' => 0,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED->value,
    ]);

    $this->postJson("/api/admin/advance-receipt/{$advance->id}/adjust", [
        'invoice_id' => $invoice->id,
        'amount' => 2000,
    ])->assertOk();

    // Should have 2 journals: one advance-receipt, one advance-adjustment
    $adjustmentJournal = Journal::withoutGlobalScopes()
        ->where('reference_type', AdvanceReceipt::class)
        ->where('reference_id', $advance->id)
        ->where('type', 'advance-adjustment')
        ->first();

    expect($adjustmentJournal)->not->toBeNull();

    $items = JournalItem::withoutGlobalScopes()
        ->where('journal_id', $adjustmentJournal->id)
        ->get();

    $drLine = $items->firstWhere('account_id', $this->advanceLiabilityAccount->id);
    $crLine = $items->firstWhere('account_id', $this->arAccount->id);

    expect((float) $drLine->dr_amount)->toBe(2000.0);
    expect((float) $crLine->cr_amount)->toBe(2000.0);
});

it('rejects adjustment exceeding advance balance', function () {
    advSetupAccounts($this);
    $invoice = advCreateApprovedInvoice($this);

    $advance = AdvanceReceipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'advance_no' => 'ADV-OVER-1',
        'advance_date' => '2026-06-01',
        'payment_method' => PaymentMethodEnum::Cash->value,
        'account_id' => $this->cashAccount->id,
        'amount' => 1000,
        'adjusted_amount' => 0,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED->value,
    ]);

    $response = $this->postJson("/api/admin/advance-receipt/{$advance->id}/adjust", [
        'invoice_id' => $invoice->id,
        'amount' => 9999,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonPath('errors.amount.0', fn ($msg) => str_contains($msg, 'exceeds'));
});

it('rejects adjustment on a draft advance', function () {
    advSetupAccounts($this);
    $invoice = advCreateApprovedInvoice($this);

    $advance = AdvanceReceipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'advance_no' => 'ADV-DRAFTADJ-1',
        'advance_date' => '2026-06-01',
        'payment_method' => PaymentMethodEnum::Cash->value,
        'account_id' => $this->cashAccount->id,
        'amount' => 1000,
        'adjusted_amount' => 0,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT->value,
    ]);

    $response = $this->postJson("/api/admin/advance-receipt/{$advance->id}/adjust", [
        'invoice_id' => $invoice->id,
        'amount' => 500,
    ]);

    $response->assertUnprocessable();
});

// ─── Void ────────────────────────────────────────────────────────────────────

it('voids an approved advance and reverses GL journal', function () {
    advSetupAccounts($this);

    $advance = AdvanceReceipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'advance_no' => 'ADV-VOID-1',
        'advance_date' => '2026-06-01',
        'payment_method' => PaymentMethodEnum::Cash->value,
        'account_id' => $this->cashAccount->id,
        'amount' => 1500,
        'adjusted_amount' => 0,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED->value,
    ]);

    // Manually create journal (simulate approval)
    $journal = Journal::create([
        'company_id' => $advance->company_id,
        'fiscal_year_id' => $advance->fiscal_year_id,
        'reference_type' => AdvanceReceipt::class,
        'reference_id' => $advance->id,
        'type' => 'advance-receipt',
        'voucher_no' => $advance->advance_no,
        'date' => $advance->advance_date,
        'create_user_id' => $advance->create_user_id,
        'approve_user_id' => $advance->approve_user_id,
        'approved_at' => $advance->approved_at,
        'status' => StatusEnum::APPROVED->value,
    ]);

    $response = $this->postJson("/api/admin/advance-receipt/{$advance->id}/void");

    $response->assertOk();
    $this->assertSoftDeleted('advance_receipts', ['id' => $advance->id]);
    $this->assertSoftDeleted('journals', ['id' => $journal->id]);
});

it('rejects voiding advance with adjusted amount', function () {
    advSetupAccounts($this);

    $advance = AdvanceReceipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'advance_no' => 'ADV-VOIDADJ-1',
        'advance_date' => '2026-06-01',
        'payment_method' => PaymentMethodEnum::Cash->value,
        'account_id' => $this->cashAccount->id,
        'amount' => 2000,
        'adjusted_amount' => 500,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED->value,
    ]);

    $response = $this->postJson("/api/admin/advance-receipt/{$advance->id}/void");

    $response->assertUnprocessable();
});

// ─── Party balance endpoint ───────────────────────────────────────────────────

it('returns party advance balance with individual advances', function () {
    advSetupAccounts($this);

    AdvanceReceipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'advance_no' => 'ADV-BAL-1',
        'advance_date' => '2026-06-01',
        'payment_method' => PaymentMethodEnum::Cash->value,
        'account_id' => $this->cashAccount->id,
        'amount' => 3000,
        'adjusted_amount' => 500,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED->value,
    ]);

    AdvanceReceipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'advance_no' => 'ADV-BAL-2',
        'advance_date' => '2026-06-02',
        'payment_method' => PaymentMethodEnum::Cash->value,
        'account_id' => $this->cashAccount->id,
        'amount' => 1000,
        'adjusted_amount' => 1000,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED->value,
    ]);

    $response = $this->getJson("/api/admin/advance-receipt-party-balance?party_id={$this->party->id}");

    $response->assertOk();
    // Only ADV-BAL-1 has balance (amount > adjusted_amount)
    expect((float) $response->json('balance'))->toBe(2500.0);
    expect($response->json('advances'))->toHaveCount(1);
    expect($response->json('advances.0.advance_no'))->toBe('ADV-BAL-1');
});
