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
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\InvoiceItem;
use App\Models\AccountGroup;
use Laravel\Sanctum\Sanctum;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Services\TenantService;
use App\Services\Nepal\IrdApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function phase4WarmCache(): void
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
    phase4WarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2081-82', 'year_code' => '8182',
        'start_date' => '2024-07-17', 'end_date' => '2025-07-16', 'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Phase4 Co', 'code' => 'P4C-'.uniqid(),
        'inventory_costing_method' => 'fifo',
        'pan' => '123456789',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Tester', 'email' => 'p4-'.uniqid().'@example.com',
        'password' => bcrypt('secret'), 'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Test Customer', 'code' => 'CUST-P4',
        'type' => 'customer', 'pan' => '999888777',
    ]);

    $product = Product::create([
        'company_id' => $this->company->id, 'name' => 'Widget', 'code' => 'WGT-P4',
    ]);
    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id, 'product_id' => $product->id,
        'sku' => 'SKU-P4', 'sales_price' => 100, 'is_default' => true,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

// ── IRD-005: Fiscal device ID validation ─────────────────────────────────────

it('accepts valid IRD fiscal device IDs — IRD-005', function (string $deviceId) {
    $service = app(IrdApiService::class);
    expect($service->isValidFiscalDeviceId($deviceId))->toBeTrue();
})->with([
    'alphanumeric' => 'ABC123',
    'with hyphens' => 'DEV-001-XYZ',
    'with underscores' => 'DEV_001_XYZ',
    'min length 3' => 'ABC',
    'max length 30' => str_repeat('A', 30),
    'mixed case' => 'FiScAlDeViCe123',
]);

it('rejects invalid IRD fiscal device IDs — IRD-005', function (string $deviceId) {
    $service = app(IrdApiService::class);
    expect($service->isValidFiscalDeviceId($deviceId))->toBeFalse();
})->with([
    'empty string' => '',
    'too short 2 chars' => 'AB',
    'too long 31 chars' => str_repeat('A', 31),
    'spaces' => 'DEV 001',
    'special chars' => 'DEV@001',
    'dot' => 'DEV.001',
]);

// ── PERF-004: vatSalesRegister uses DB aggregation ───────────────────────────

it('vatSalesRegister returns aggregated rows including zero_rated_amount — PERF-004', function () {
    $today = now()->toDateString();

    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'invoice_no' => 'INV-P4-001',
        'invoice_date' => $today,
        'party_id' => $this->party->id,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
        'approved_at' => now(),
    ]);

    // Taxable: 1000 + VAT 130
    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => null,
        'quantity' => 1, 'rate' => 1000, 'discount_amount' => 0,
        'tax_amount' => 130, 'tax_line_type' => 'taxable',
    ]);

    // Zero-rated: 500
    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => null,
        'quantity' => 1, 'rate' => 500, 'discount_amount' => 0,
        'tax_amount' => 0, 'tax_line_type' => 'zero_rated',
    ]);

    // Exempt: 200
    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => null,
        'quantity' => 1, 'rate' => 200, 'discount_amount' => 0,
        'tax_amount' => 0, 'tax_line_type' => 'exempt',
    ]);

    $response = $this->getJson("/api/admin/account-report/vat-sales-register?start_date={$today}&end_date={$today}");

    $response->assertOk();

    $rows = $response->json('data.rows');
    expect($rows)->toHaveCount(1);

    $row = $rows[0];
    expect((float) $row['taxable_amount'])->toEqual(1000.0);
    expect((float) $row['vat_amount'])->toEqual(130.0);
    expect((float) $row['zero_rated_amount'])->toEqual(500.0);
    expect((float) $row['exempt_amount'])->toEqual(200.0);
    expect((float) $row['total_amount'])->toEqual(1830.0);

    $summary = $response->json('data.summary');
    expect((float) $summary['taxable_amount'])->toEqual(1000.0);
    expect((float) $summary['vat_amount'])->toEqual(130.0);
    expect((float) $summary['zero_rated_amount'])->toEqual(500.0);
    expect((float) $summary['exempt_amount'])->toEqual(200.0);
    expect((float) $summary['total_amount'])->toEqual(1830.0);
});

it('vatSalesRegister excludes voided invoices — PERF-004', function () {
    $today = now()->toDateString();

    $voidedInvoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'invoice_no' => 'INV-P4-VOID',
        'invoice_date' => $today,
        'party_id' => $this->party->id,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
        'approved_at' => now(),
        'voided_at' => now(),
    ]);

    InvoiceItem::create([
        'invoice_id' => $voidedInvoice->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => null,
        'quantity' => 1, 'rate' => 1000, 'discount_amount' => 0,
        'tax_amount' => 130, 'tax_line_type' => 'taxable',
    ]);

    $response = $this->getJson("/api/admin/account-report/vat-sales-register?start_date={$today}&end_date={$today}");

    $response->assertOk();
    expect($response->json('data.rows'))->toBeEmpty();
});

// ── PERF-004: vatPurchaseRegister uses DB aggregation ────────────────────────

it('vatPurchaseRegister returns aggregated rows including zero_rated_amount — PERF-004', function () {
    $today = now()->toDateString();

    $supplier = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Supplier Co', 'code' => 'SUP-P4',
        'type' => 'supplier', 'pan' => '111222333',
    ]);

    $bill = Bill::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'bill_no' => 'BILL-P4-001',
        'bill_date' => $today,
        'party_id' => $supplier->id,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
        'approved_at' => now(),
    ]);

    // Taxable: 800 + VAT 104
    BillItem::create([
        'bill_id' => $bill->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => null,
        'quantity' => 1, 'rate' => 800, 'discount_amount' => 0,
        'tax_amount' => 104, 'tax_line_type' => 'taxable',
    ]);

    // Zero-rated: 300
    BillItem::create([
        'bill_id' => $bill->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => null,
        'quantity' => 1, 'rate' => 300, 'discount_amount' => 0,
        'tax_amount' => 0, 'tax_line_type' => 'zero_rated',
    ]);

    // Exempt: 100
    BillItem::create([
        'bill_id' => $bill->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => null,
        'quantity' => 1, 'rate' => 100, 'discount_amount' => 0,
        'tax_amount' => 0, 'tax_line_type' => 'exempt',
    ]);

    $response = $this->getJson("/api/admin/account-report/vat-purchase-register?start_date={$today}&end_date={$today}");

    $response->assertOk();

    $rows = $response->json('data.rows');
    expect($rows)->toHaveCount(1);

    $row = $rows[0];
    expect((float) $row['taxable_amount'])->toEqual(800.0);
    expect((float) $row['input_vat'])->toEqual(104.0);
    expect((float) $row['zero_rated_amount'])->toEqual(300.0);
    expect((float) $row['exempt_amount'])->toEqual(100.0);
    expect((float) $row['total_amount'])->toEqual(1304.0);

    $summary = $response->json('data.summary');
    expect((float) $summary['taxable_amount'])->toEqual(800.0);
    expect((float) $summary['input_vat'])->toEqual(104.0);
    expect((float) $summary['zero_rated_amount'])->toEqual(300.0);
    expect((float) $summary['exempt_amount'])->toEqual(100.0);
    expect((float) $summary['total_amount'])->toEqual(1304.0);
});

// ── PERF-003: ClosingEntryService nominal account collection ─────────────────

it('closing entry posts successfully for a company with income and expense groups — PERF-003', function () {
    // Build a minimal COA: Income group and Expense group
    $incomeGroup = AccountGroup::create([
        'company_id' => $this->company->id, 'name' => 'Income', 'code' => 'INC-P4',
    ]);
    $expenseGroup = AccountGroup::create([
        'company_id' => $this->company->id, 'name' => 'Expenses', 'code' => 'EXP-P4',
    ]);
    $equityGroup = AccountGroup::create([
        'company_id' => $this->company->id, 'name' => 'Equity', 'code' => 'EQ-P4',
    ]);

    $salesAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => $incomeGroup->id,
        'name' => 'Sales Revenue', 'code' => 'SALES-P4', 'is_active' => true,
    ]);
    $rentAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => $expenseGroup->id,
        'name' => 'Rent Expense', 'code' => 'RENT-P4', 'is_active' => true,
    ]);
    $retainedEarnings = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => $equityGroup->id,
        'name' => 'Retained Earnings', 'code' => 'RE-P4', 'is_active' => true,
    ]);

    AccountSetting::withoutGlobalScopes()->create([
        'company_id' => $this->company->id,
        'retained_earnings_account_id' => $retainedEarnings->id,
    ]);

    $response = $this->postJson('/api/admin/accounting-period/closing-entry', [
        'fiscal_year_id' => $this->fiscalYear->id,
    ]);

    // No income/expense journals exist yet — service returns 'nothing_to_close' (200)
    $response->assertOk();
    expect($response->json('data.reason'))->toBe('nothing_to_close');
});

it('closing entry is idempotent — second post returns already_posted — PERF-003', function () {
    $incomeGroup = AccountGroup::create([
        'company_id' => $this->company->id, 'name' => 'Revenue', 'code' => 'REV-P4',
    ]);
    $equityGroup = AccountGroup::create([
        'company_id' => $this->company->id, 'name' => 'Equity', 'code' => 'EQ2-P4',
    ]);
    $retainedEarnings = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => $equityGroup->id,
        'name' => 'Retained Earnings', 'code' => 'RE2-P4', 'is_active' => true,
    ]);

    AccountSetting::withoutGlobalScopes()->create([
        'company_id' => $this->company->id,
        'retained_earnings_account_id' => $retainedEarnings->id,
    ]);

    // Post once (will be nothing_to_close, no journal created)
    $this->postJson('/api/admin/accounting-period/closing-entry', [
        'fiscal_year_id' => $this->fiscalYear->id,
    ])->assertOk();

    // Manually create a CLOSING_ENTRY journal to simulate "already posted"
    \App\Models\Journal::withoutGlobalScopes()->create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'type' => \App\Enums\JournalTypeEnum::CLOSING_ENTRY,
        'voucher_no' => 'CLOSE-JV-TEST',
        'date' => $this->fiscalYear->end_date->toDateString(),
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    // Second attempt must be blocked
    $response = $this->postJson('/api/admin/accounting-period/closing-entry', [
        'fiscal_year_id' => $this->fiscalYear->id,
    ]);

    $response->assertUnprocessable();
    expect($response->json('data.reason'))->toBe('already_posted');
});
