<?php

use App\Models\Bill;
use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\BillItem;
use App\Enums\StatusEnum;
use App\Models\Warehouse;
use App\Models\CreditNote;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Enums\ChangeTypeEnum;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use App\Services\Accounting\GlAccountConfigGuard;
use App\Services\Inventory\InventoryCostCalculator;
use App\Services\Inventory\InventoryLayerReceiptService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function auditWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

function auditSeedStock(object $test, int $qty): void
{
    auditWarmCache();

    $bill = Bill::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'bill_no' => 'BILL-AUD-'.uniqid(),
        'bill_date' => now()->toDateString(),
        'create_user_id' => $test->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $item = BillItem::create([
        'bill_id' => $bill->id,
        'product_variant_id' => $test->variant->id,
        'warehouse_id' => $test->warehouse->id,
        'quantity' => $qty,
        'rate' => 50,
        'discount_amount' => 0,
    ]);

    DB::transaction(function () use ($test, $bill, $item, $qty) {
        app(InventoryLayerReceiptService::class)->receive(
            $test->company,
            $bill,
            $test->variant->id,
            $test->warehouse->id,
            $qty,
            InventoryCostCalculator::unitCostFromBillItem($item),
            ChangeTypeEnum::PURCHASE,
            $test->user->id,
            null,
            $item->id,
        );
    });
}

function auditAccountSetting(object $test): Account
{
    $account = Account::create([
        'company_id' => $test->company->id,
        'account_group_id' => null,
        'name' => 'Audit Cash',
        'code' => 'AUD-CASH-'.uniqid(),
    ]);

    AccountSetting::create([
        'company_id' => $test->company->id,
        'cash_sales_account_id' => $account->id,
        'bank_sales_account_id' => $account->id,
        'customer_account_id' => $account->id,
        'sales_account_id' => $account->id,
    ]);

    return $account;
}

function auditInvoicePayload(object $test, array $overrides = []): array
{
    return array_merge([
        'invoice_date' => now()->toDateString(),
        'party_id' => $test->customer->id,
        'status' => StatusEnum::DRAFT->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'items' => [[
            'product_variant_id' => $test->variant->id,
            'warehouse_id' => $test->warehouse->id,
            'quantity' => 1,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'tax_line_type' => 'taxable',
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
        ]],
    ], $overrides);
}

beforeEach(function () {
    auditWarmCache();
    TenantService::setCompanyId(null);
    TenantService::setBranchId(null);

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Audit Test Co',
        'code' => 'AUDITCO',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Audit Tester',
        'email' => 'audit-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Main', 'code' => 'AUD-W',
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Audit Widget', 'code' => 'AUD-'.uniqid(),
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'AUD-SKU-'.uniqid(),
        'sales_price' => 100,
        'is_default' => true,
    ]);

    $this->customer = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Audit Customer',
        'code' => 'AUD-CUST-'.uniqid(),
        'type' => PartyTypeEnum::CUSTOMER,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

// ─── Migration: voided_at column ──────────────────────────────────────────────

it('credit_notes table has voided_at column after migration', function () {
    expect(Schema::hasColumn('credit_notes', 'voided_at'))->toBeTrue();
});

it('voided_at can be written and read back via the CreditNote model', function () {
    $creditNote = CreditNote::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->customer->id,
        'credit_note_no' => 'CN-MIGTEST-'.uniqid(),
        'credit_note_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
        'approved_at' => now(),
        'approve_user_id' => $this->user->id,
    ]);

    expect($creditNote->voided_at)->toBeNull();

    $creditNote->update(['voided_at' => now()]);

    expect($creditNote->fresh()->voided_at)->not->toBeNull();
});

it('voiding an approved credit note via the API sets voided_at', function () {
    // reverseApprovedCreditNote and reverseForReference both safely no-op
    // when no StockMovements/Journals exist for the document.
    $creditNote = CreditNote::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->customer->id,
        'credit_note_no' => 'CN-VOID-'.uniqid(),
        'credit_note_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
        'approved_at' => now(),
        'approve_user_id' => $this->user->id,
    ]);

    $response = $this->postJson("/api/admin/credit-note/{$creditNote->id}/void");

    $response->assertOk();
    expect($creditNote->fresh()->voided_at)->not->toBeNull();
});

// ─── BUG-002: InvoiceService must persist computed order discount amount ───────

it('BUG-002: invoice with a fixed order discount stores the correct discount.amount', function () {
    // 2 × 100 = 200 gross; fixed discount 30 → discount.amount must be 30
    $response = $this->postJson('/api/admin/invoice', auditInvoicePayload($this, [
        'order_discount_type' => 'fixed',
        'order_discount_value' => 30,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 2,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'tax_line_type' => 'taxable',
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
        ]],
    ]));

    $response->assertCreated();

    $invoice = Invoice::first();
    $discount = DB::table('discounts')
        ->where('discountable_type', Invoice::class)
        ->where('discountable_id', $invoice->id)
        ->first();

    expect((float) $discount->amount)->toBe(30.0);
});

it('BUG-002: invoice with a percent order discount stores the computed discount.amount', function () {
    // 2 × 100 = 200 gross; 10% → discount.amount must be 20
    $response = $this->postJson('/api/admin/invoice', auditInvoicePayload($this, [
        'order_discount_type' => 'percent',
        'order_discount_value' => 10,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 2,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'tax_line_type' => 'taxable',
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
        ]],
    ]));

    $response->assertCreated();

    $invoice = Invoice::first();
    $discount = DB::table('discounts')
        ->where('discountable_type', Invoice::class)
        ->where('discountable_id', $invoice->id)
        ->first();

    expect((float) $discount->amount)->toBe(20.0);
});

it('BUG-002: approved invoice GL journal DR total equals grand total minus order discount', function () {
    auditSeedStock($this, 10);
    auditAccountSetting($this);

    // 2 × 100 = 200 gross; fixed discount 30 → net 170; GL AR debit must be 170
    $createRes = $this->postJson('/api/admin/invoice', auditInvoicePayload($this, [
        'order_discount_type' => 'fixed',
        'order_discount_value' => 30,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 2,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'tax_line_type' => 'taxable',
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
        ]],
    ]));
    $createRes->assertCreated();

    $invoice = Invoice::first();
    $this->postJson("/api/admin/invoice/{$invoice->id}/approve")->assertOk();

    $journal = DB::table('journals')
        ->where('reference_type', Invoice::class)
        ->where('reference_id', $invoice->id)
        ->first();

    expect($journal)->not->toBeNull('Invoice GL journal must be created on approval');

    $drTotal = round((float) DB::table('journal_items')->where('journal_id', $journal->id)->sum('dr_amount'), 2);
    $crTotal = round((float) DB::table('journal_items')->where('journal_id', $journal->id)->sum('cr_amount'), 2);

    expect($drTotal)->toBe($crTotal, 'GL journal must balance');
    expect($drTotal)->toBe(170.0, 'AR debit must equal net grand total (200 − 30 discount = 170)');
});

// ─── BUG-003: dueInvoices endpoint must exclude voided invoices ───────────────

it('BUG-003: voided invoice is excluded from the dueInvoices endpoint', function () {
    auditSeedStock($this, 5);
    auditAccountSetting($this);

    $createRes = $this->postJson('/api/admin/invoice', auditInvoicePayload($this));
    $createRes->assertCreated();
    $invoice = Invoice::first();

    $this->postJson("/api/admin/invoice/{$invoice->id}/approve")->assertOk();

    // Before void: invoice should appear with a due amount
    $dueBefore = $this->getJson("/api/admin/invoice/due?party_id={$this->customer->id}");
    $dueBefore->assertOk();
    expect(count($dueBefore->json('data')))->toBe(1);

    $this->postJson("/api/admin/invoice/{$invoice->id}/void")->assertOk();

    // After void: no invoice should appear
    $dueAfter = $this->getJson("/api/admin/invoice/due?party_id={$this->customer->id}");
    $dueAfter->assertOk();
    expect($dueAfter->json('data'))->toBeEmpty();
});

// ─── BUG-004: Cumulative credit note return validation ────────────────────────

it('BUG-004: creating a credit note that exceeds the remaining returnable quantity is rejected', function () {
    auditSeedStock($this, 10);
    auditAccountSetting($this);

    // Approve invoice with qty=5
    $createRes = $this->postJson('/api/admin/invoice', auditInvoicePayload($this, [
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 5,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'tax_line_type' => 'taxable',
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
        ]],
    ]));
    $createRes->assertCreated();
    $invoice = Invoice::first();
    $this->postJson("/api/admin/invoice/{$invoice->id}/approve")->assertOk();

    $invoiceItemId = DB::table('invoice_items')->where('invoice_id', $invoice->id)->value('id');

    // CN1: return qty=3 → approve (5 − 3 = 2 remaining)
    $cn1Res = $this->postJson('/api/admin/credit-note', [
        'credit_note_date' => now()->toDateString(),
        'party_id' => $this->customer->id,
        'invoice_id' => $invoice->id,
        'status' => StatusEnum::DRAFT->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 3,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
            'invoice_item_id' => $invoiceItemId,
        ]],
    ]);
    $cn1Res->assertCreated();
    $cn1 = CreditNote::first();
    $this->postJson("/api/admin/credit-note/{$cn1->id}/approve")->assertOk();

    // CN2: try qty=3, only 2 remain → must be rejected
    $cn2Res = $this->postJson('/api/admin/credit-note', [
        'credit_note_date' => now()->toDateString(),
        'party_id' => $this->customer->id,
        'invoice_id' => $invoice->id,
        'status' => StatusEnum::DRAFT->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 3,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
            'invoice_item_id' => $invoiceItemId,
        ]],
    ]);

    $cn2Res->assertUnprocessable();
    $cn2Res->assertJsonValidationErrors(['items.0.quantity']);
});

it('BUG-004: credit note exactly at the remaining returnable quantity is accepted', function () {
    auditSeedStock($this, 10);
    auditAccountSetting($this);

    // Approve invoice with qty=5
    $createRes = $this->postJson('/api/admin/invoice', auditInvoicePayload($this, [
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 5,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'tax_line_type' => 'taxable',
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
        ]],
    ]));
    $createRes->assertCreated();
    $invoice = Invoice::first();
    $this->postJson("/api/admin/invoice/{$invoice->id}/approve")->assertOk();

    $invoiceItemId = DB::table('invoice_items')->where('invoice_id', $invoice->id)->value('id');

    // CN1: return qty=3 → approve
    $cn1Res = $this->postJson('/api/admin/credit-note', [
        'credit_note_date' => now()->toDateString(),
        'party_id' => $this->customer->id,
        'invoice_id' => $invoice->id,
        'status' => StatusEnum::DRAFT->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 3,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
            'invoice_item_id' => $invoiceItemId,
        ]],
    ]);
    $cn1Res->assertCreated();
    $cn1 = CreditNote::first();
    $this->postJson("/api/admin/credit-note/{$cn1->id}/approve")->assertOk();

    // CN2: return exactly remaining qty=2 → must succeed
    $cn2Res = $this->postJson('/api/admin/credit-note', [
        'credit_note_date' => now()->toDateString(),
        'party_id' => $this->customer->id,
        'invoice_id' => $invoice->id,
        'status' => StatusEnum::DRAFT->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 2,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
            'invoice_item_id' => $invoiceItemId,
        ]],
    ]);

    $cn2Res->assertCreated();
});

// ─── BUG-005/BUG-007: Receipt allocation must be blocked against voided invoices

it('BUG-005: creating a receipt allocation against a voided invoice returns 422', function () {
    auditSeedStock($this, 5);
    $cashAccount = auditAccountSetting($this);

    // Create, approve, then void an invoice
    $createRes = $this->postJson('/api/admin/invoice', auditInvoicePayload($this));
    $createRes->assertCreated();
    $invoice = Invoice::first();
    $this->postJson("/api/admin/invoice/{$invoice->id}/approve")->assertOk();
    $this->postJson("/api/admin/invoice/{$invoice->id}/void")->assertOk();

    // Attempt to allocate a receipt against the voided invoice
    $receiptRes = $this->postJson('/api/admin/receipt', [
        'receipt_date' => now()->toDateString(),
        'party_id' => $this->customer->id,
        'payment_method' => 'cash',
        'account_id' => $cashAccount->id,
        'status' => StatusEnum::DRAFT->value,
        'allocations' => [[
            'invoice_id' => $invoice->id,
            'amount' => 100,
        ]],
    ]);

    $receiptRes->assertStatus(422);
});

// ─── BUG-009: GlAccountConfigGuard must isolate by company ────────────────────

it('BUG-009: GlAccountConfigGuard reports missing accounts when no settings exist for the company', function () {
    TenantService::setCompanyId($this->company->id);

    $guard = app(GlAccountConfigGuard::class);
    $missing = $guard->missingSalesAccounts(hasTax: false);

    expect($missing)->toContain('Accounts Receivable (customer) account');
    expect($missing)->toContain('Sales Revenue account');
});

it('BUG-009: GlAccountConfigGuard returns no missing accounts when settings are configured', function () {
    auditAccountSetting($this);
    TenantService::setCompanyId($this->company->id);

    $guard = app(GlAccountConfigGuard::class);
    $missing = $guard->missingSalesAccounts(hasTax: false);

    expect($missing)->toBeEmpty();
});

it('BUG-009: GlAccountConfigGuard does not bleed account settings across companies', function () {
    // $this->company (Company B) gets full account settings; Company A gets none.
    auditAccountSetting($this);

    $companyA = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Company A No Settings',
        'code' => 'CMPANO-'.uniqid(),
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $guard = app(GlAccountConfigGuard::class);

    TenantService::setCompanyId($companyA->id);
    $missingA = $guard->missingSalesAccounts(hasTax: false);

    TenantService::setCompanyId($this->company->id);
    $missingB = $guard->missingSalesAccounts(hasTax: false);

    expect($missingA)->not->toBeEmpty('Company A should report missing accounts');
    expect($missingB)->toBeEmpty('Company B has full account settings');
});
