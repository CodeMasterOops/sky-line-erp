<?php

use App\Models\User;
use App\Models\Party;
use App\Models\Branch;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Receipt;
use App\Enums\StatusEnum;
use App\Models\CreditNote;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\InvoiceItem;
use Laravel\Sanctum\Sanctum;
use App\Models\AccountSetting;
use App\Models\CreditNoteItem;
use App\Models\ProductVariant;
use App\Models\ProductCategory;
use App\Services\TenantService;
use App\Models\ReceiptAllocation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function srWarmCache(): void
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
    srWarmCache();
    TenantService::setBranchId(null);

    $this->fiscalYear = FiscalYear::create([
        'year_name' => 'SR-Test-FY',
        'year_code' => 'SRFY',
        'start_date' => '2024-07-17',
        'end_date' => '2025-07-16',
        'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'SR Test Co',
        'code' => 'SRTC-'.uniqid(),
        'inventory_costing_method' => 'fifo',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'SR Tester',
        'email' => 'sr-'.uniqid().'@test.com',
        'password' => bcrypt('secret'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Test Customer',
        'code' => 'CUST-'.uniqid(),
        'type' => 'customer',
    ]);

    $arAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'AR',
        'code' => 'AR-SR-'.uniqid(),
    ]);

    $this->arAccountId = $arAccount->id;

    $salesAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Sales',
        'code' => 'SALES-SR-'.uniqid(),
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'customer_account_id' => $arAccount->id,
        'sales_account_id' => $salesAccount->id,
        'cash_sales_account_id' => $arAccount->id,
        'bank_sales_account_id' => $arAccount->id,
    ]);

    $this->category = ProductCategory::create([
        'company_id' => $this->company->id,
        'name' => 'Electronics',
    ]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Test Product',
        'code' => 'PROD-SR-'.uniqid(),
        'product_category_id' => $this->category->id,
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'sku' => 'SKU-SR-'.uniqid(),
        'sales_price' => 100,
        'purchase_price' => 60,
        'is_default' => true,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

function srMakeInvoice(array $overrides = [], float $qty = 2, float $rate = 100, float $discount = 0, float $tax = 0): Invoice
{
    $invoice = Invoice::create(array_merge([
        'company_id' => test()->company->id,
        'fiscal_year_id' => test()->fiscalYear->id,
        'party_id' => test()->party->id,
        'invoice_no' => 'INV-SR-'.uniqid(),
        'invoice_date' => '2024-10-01',
        'status' => StatusEnum::APPROVED,
        'create_user_id' => test()->user->id,
        'approve_user_id' => test()->user->id,
        'approved_at' => now(),
    ], $overrides));

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_variant_id' => test()->variant->id,
        'quantity' => $qty,
        'rate' => $rate,
        'discount_amount' => $discount,
        'tax_amount' => $tax,
    ]);

    return $invoice;
}

// ─── Sales Summary ────────────────────────────────────────────────────────────

it('sales summary returns correct KPI totals', function () {
    srMakeInvoice(['invoice_date' => '2024-10-01'], qty: 2, rate: 100, tax: 20);
    srMakeInvoice(['invoice_date' => '2024-10-15'], qty: 1, rate: 200, discount: 10);

    $response = $this->getJson('/api/admin/sales-report/sales-summary?from_date=2024-10-01&to_date=2024-10-31');

    $response->assertOk();
    $data = $response->json('data');

    expect($data)->toHaveKeys(['period', 'summary', 'party_options']);
    expect($data['summary']['invoice_count'])->toBe(2);
    expect($data['summary']['subtotal'])->toEqual(400.0);     // (2*100) + (1*200)
    expect($data['summary']['total_discount'])->toEqual(10.0);
    expect($data['summary']['tax_amount'])->toEqual(20.0);
    expect($data['summary']['net_sales'])->toEqual(410.0);    // 400 - 10 + 20
});

it('sales summary excludes voided invoices', function () {
    srMakeInvoice(['invoice_date' => '2024-10-01', 'voided_at' => now()]);
    srMakeInvoice(['invoice_date' => '2024-10-01']);

    $response = $this->getJson('/api/admin/sales-report/sales-summary?from_date=2024-10-01&to_date=2024-10-31');

    $response->assertOk();
    expect($response->json('data.summary.invoice_count'))->toBe(1);
});

it('sales summary counts credit note returns', function () {
    srMakeInvoice(['invoice_date' => '2024-10-01']);

    $cn = CreditNote::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'credit_note_no' => 'CN-SR-'.uniqid(),
        'credit_note_date' => '2024-10-05',
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);
    CreditNoteItem::create([
        'credit_note_id' => $cn->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
        'rate' => 50,
        'discount_amount' => 0,
        'tax_amount' => 0,
    ]);

    $response = $this->getJson('/api/admin/sales-report/sales-summary?from_date=2024-10-01&to_date=2024-10-31');

    $response->assertOk();
    expect($response->json('data.summary.return_count'))->toBe(1);
    expect($response->json('data.summary.total_returns'))->toEqual(50.0);
});

// ─── Daily Sales ──────────────────────────────────────────────────────────────

it('daily sales groups invoices by day', function () {
    srMakeInvoice(['invoice_date' => '2024-10-01'], rate: 100);
    srMakeInvoice(['invoice_date' => '2024-10-01'], rate: 50);
    srMakeInvoice(['invoice_date' => '2024-10-03'], rate: 200);

    $response = $this->getJson('/api/admin/sales-report/daily-sales?from_date=2024-10-01&to_date=2024-10-05');

    $response->assertOk();
    $rows = $response->json('data.rows');

    expect($rows)->toHaveCount(2);
    $day1 = collect($rows)->firstWhere('sale_date', '2024-10-01');
    expect($day1['invoice_count'])->toBe(2);
    expect($day1['net_sales'])->toEqual(300.0);  // (2*100) + (2*50)
});

// ─── Monthly Sales ────────────────────────────────────────────────────────────

it('monthly sales groups invoices by month with labels', function () {
    srMakeInvoice(['invoice_date' => '2024-10-05'], rate: 100);
    srMakeInvoice(['invoice_date' => '2024-10-20'], rate: 100);
    srMakeInvoice(['invoice_date' => '2024-11-10'], rate: 150);

    $response = $this->getJson('/api/admin/sales-report/monthly-sales?from_date=2024-10-01&to_date=2024-11-30');

    $response->assertOk();
    $rows = $response->json('data.rows');

    expect($rows)->toHaveCount(2);
    $oct = collect($rows)->firstWhere('month', 10);
    expect($oct['invoice_count'])->toBe(2);
    expect($oct['month_label'])->toBe('Oct 2024');
    expect($oct['year'])->toBe(2024);
});

// ─── Yearly Sales ─────────────────────────────────────────────────────────────

it('yearly sales groups invoices by year', function () {
    srMakeInvoice(['invoice_date' => '2024-06-01'], rate: 100);
    srMakeInvoice(['invoice_date' => '2025-01-01'], rate: 200);

    $response = $this->getJson('/api/admin/sales-report/yearly-sales?from_date=2024-01-01&to_date=2025-12-31');

    $response->assertOk();
    $rows = $response->json('data.rows');

    expect($rows)->toHaveCount(2);
    $y2024 = collect($rows)->firstWhere('sale_year', '2024');
    expect($y2024)->not->toBeNull();
    expect($y2024['net_sales'])->toEqual(200.0);
});

// ─── Customer Wise Sales ──────────────────────────────────────────────────────

it('customer wise sales groups by customer with outstanding balance', function () {
    $party2 = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Customer B',
        'code' => 'CUST-B-'.uniqid(),
        'type' => 'customer',
    ]);

    srMakeInvoice(['invoice_date' => '2024-10-01', 'party_id' => $this->party->id], rate: 100);
    srMakeInvoice(['invoice_date' => '2024-10-01', 'party_id' => $party2->id], rate: 300);

    $response = $this->getJson('/api/admin/sales-report/customer-wise-sales?from_date=2024-10-01&to_date=2024-10-31');

    $response->assertOk();
    $rows = $response->json('data.rows');

    expect($rows)->toHaveCount(2);
    $row = collect($rows)->firstWhere('party_name', 'Customer B');
    expect($row['net_sales'])->toEqual(600.0);
    expect($row['outstanding'])->toEqual(600.0);
});

// ─── Category Wise Sales ──────────────────────────────────────────────────────

it('category wise sales groups by product category', function () {
    srMakeInvoice(['invoice_date' => '2024-10-01'], rate: 150);

    $response = $this->getJson('/api/admin/sales-report/category-wise-sales?from_date=2024-10-01&to_date=2024-10-31');

    $response->assertOk();
    $rows = $response->json('data.rows');

    expect($rows)->toHaveCount(1);
    expect($rows[0]['category_name'])->toBe('Electronics');
    expect($rows[0]['net_sales'])->toEqual(300.0);  // qty=2 * rate=150
});

// ─── Sales Return ─────────────────────────────────────────────────────────────

it('sales return lists credit notes with correct totals', function () {
    $cn = CreditNote::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'credit_note_no' => 'CN-SR-001',
        'credit_note_date' => '2024-10-10',
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);
    CreditNoteItem::create([
        'credit_note_id' => $cn->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 2,
        'rate' => 80,
        'discount_amount' => 5,
        'tax_amount' => 10,
    ]);

    $response = $this->getJson('/api/admin/sales-report/sales-return?from_date=2024-10-01&to_date=2024-10-31');

    $response->assertOk();
    $rows = $response->json('data.rows');
    $summary = $response->json('data.summary');

    expect($rows)->toHaveCount(1);
    expect($rows[0]['total_amount'])->toEqual(165.0);  // (2*80) - 5 + 10
    expect($summary['return_count'])->toBe(1);
    expect($summary['total_amount'])->toEqual(165.0);
});

it('sales return excludes voided credit notes', function () {
    CreditNote::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'credit_note_no' => 'CN-VOID-'.uniqid(),
        'credit_note_date' => '2024-10-10',
        'status' => StatusEnum::APPROVED,
        'voided_at' => now(),
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    $response = $this->getJson('/api/admin/sales-report/sales-return?from_date=2024-10-01&to_date=2024-10-31');

    $response->assertOk();
    expect($response->json('data.summary.return_count'))->toBe(0);
});

// ─── Outstanding Sales ────────────────────────────────────────────────────────

it('outstanding sales shows only invoices with balance due', function () {
    $paid = srMakeInvoice(['invoice_date' => '2024-10-01', 'due_date' => '2024-11-01'], rate: 100);
    srMakeInvoice(['invoice_date' => '2024-10-01', 'due_date' => '2024-11-01'], rate: 200);

    $receipt = Receipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'receipt_no' => 'REC-SR-'.uniqid(),
        'receipt_date' => '2024-10-05',
        'payment_method' => 'cash',
        'account_id' => $this->arAccountId,
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);
    ReceiptAllocation::create([
        'receipt_id' => $receipt->id,
        'invoice_id' => $paid->id,
        'amount' => 200,  // fully pays qty=2 * rate=100
    ]);

    $response = $this->getJson('/api/admin/sales-report/outstanding-sales');

    $response->assertOk();
    $rows = $response->json('data.rows');

    expect($rows)->toHaveCount(1);
    expect($rows[0]['balance_due'])->toEqual(400.0);  // qty=2 * rate=200, nothing paid
});

it('outstanding sales marks overdue invoices', function () {
    srMakeInvoice([
        'invoice_date' => '2024-01-01',
        'due_date' => '2024-02-01',
    ], rate: 100);

    $response = $this->getJson('/api/admin/sales-report/outstanding-sales');

    $response->assertOk();
    $rows = $response->json('data.rows');
    expect($rows)->not->toBeEmpty();
    expect($rows[0]['is_overdue'])->toBeTrue();
    expect($rows[0]['days_overdue'])->toBeGreaterThan(0);
});

// ─── Sales Tax ────────────────────────────────────────────────────────────────

it('sales tax report groups and sums tax amounts', function () {
    srMakeInvoice(['invoice_date' => '2024-10-01'], rate: 100, tax: 13);
    srMakeInvoice(['invoice_date' => '2024-10-10'], rate: 200, tax: 26);

    $response = $this->getJson('/api/admin/sales-report/sales-tax?from_date=2024-10-01&to_date=2024-10-31');

    $response->assertOk();
    $data = $response->json('data');

    expect($data)->toHaveKeys(['period', 'rows', 'summary']);
    expect($data['summary']['tax_amount'])->toEqual(39.0);
});

// ─── Sales Profit ─────────────────────────────────────────────────────────────

it('sales profit computes gross profit using purchase price', function () {
    srMakeInvoice(['invoice_date' => '2024-10-01'], qty: 2, rate: 100);

    $response = $this->getJson('/api/admin/sales-report/sales-profit?from_date=2024-10-01&to_date=2024-10-31');

    $response->assertOk();
    $rows = $response->json('data.rows');

    expect($rows)->toHaveCount(1);
    expect($rows[0]['net_revenue'])->toEqual(200.0);
    expect($rows[0]['total_cost'])->toEqual(120.0);   // 2 * purchase_price(60)
    expect($rows[0]['gross_profit'])->toEqual(80.0);
    expect($rows[0]['margin_pct'])->toEqual(40.0);    // 80/200*100
});

it('sales profit summary matches row totals', function () {
    srMakeInvoice(['invoice_date' => '2024-10-01'], qty: 3, rate: 100);

    $response = $this->getJson('/api/admin/sales-report/sales-profit?from_date=2024-10-01&to_date=2024-10-31');

    $response->assertOk();
    $summary = $response->json('data.summary');

    expect($summary['net_revenue'])->toEqual(300.0);
    expect($summary['total_cost'])->toEqual(180.0);
    expect($summary['gross_profit'])->toEqual(120.0);
});

// ─── Discount Report ──────────────────────────────────────────────────────────

it('discount report only includes invoices with discounts', function () {
    srMakeInvoice(['invoice_date' => '2024-10-01'], discount: 0);
    srMakeInvoice(['invoice_date' => '2024-10-05'], discount: 15);

    $response = $this->getJson('/api/admin/sales-report/discount-report?from_date=2024-10-01&to_date=2024-10-31');

    $response->assertOk();
    $rows = $response->json('data.rows');

    expect($rows)->toHaveCount(1);
    expect($rows[0]['line_discount'])->toEqual(15.0);
    expect($rows[0]['total_discount'])->toEqual(15.0);
});

it('discount report summary totals all discounts', function () {
    srMakeInvoice(['invoice_date' => '2024-10-01'], discount: 10);
    srMakeInvoice(['invoice_date' => '2024-10-05'], discount: 20);

    $response = $this->getJson('/api/admin/sales-report/discount-report?from_date=2024-10-01&to_date=2024-10-31');

    $response->assertOk();
    expect($response->json('data.summary.total_discount'))->toEqual(30.0);
});

// ─── Sales Ledger ─────────────────────────────────────────────────────────────

it('sales ledger returns empty structure when party_id is missing', function () {
    $response = $this->getJson('/api/admin/sales-report/sales-ledger?from_date=2024-10-01&to_date=2024-10-31');

    $response->assertOk();
    expect($response->json('data.party'))->toBeNull();
    expect($response->json('data.rows'))->toBeEmpty();
});

it('sales ledger shows invoices as debit and receipts as credit with running balance', function () {
    $invoice = srMakeInvoice(['invoice_date' => '2024-10-01'], qty: 1, rate: 500);

    $receipt = Receipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'receipt_no' => 'REC-LED-'.uniqid(),
        'receipt_date' => '2024-10-10',
        'payment_method' => 'cash',
        'account_id' => $this->arAccountId,
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);
    ReceiptAllocation::create([
        'receipt_id' => $receipt->id,
        'invoice_id' => $invoice->id,
        'amount' => 200,
    ]);

    $response = $this->getJson("/api/admin/sales-report/sales-ledger?party_id={$this->party->id}&from_date=2024-10-01&to_date=2024-10-31");

    $response->assertOk();
    $data = $response->json('data');

    expect($data['party']['id'])->toBe($this->party->id);
    expect($data['rows'])->toHaveCount(2);

    $invoiceRow = collect($data['rows'])->firstWhere('type', 'Invoice');
    $receiptRow = collect($data['rows'])->firstWhere('type', 'Receipt');

    expect($invoiceRow['debit'])->toEqual(500.0);
    expect($receiptRow['credit'])->toEqual(200.0);
    expect($data['closing_balance'])->toEqual(300.0);
});

it('sales ledger computes opening balance from prior period transactions', function () {
    srMakeInvoice(['invoice_date' => '2024-09-01'], qty: 1, rate: 300);

    $response = $this->getJson("/api/admin/sales-report/sales-ledger?party_id={$this->party->id}&from_date=2024-10-01&to_date=2024-10-31");

    $response->assertOk();
    expect($response->json('data.opening_balance'))->toEqual(300.0);
    expect($response->json('data.rows'))->toBeEmpty();
    expect($response->json('data.closing_balance'))->toEqual(300.0);
});

// ─── Branch isolation ─────────────────────────────────────────────────────────

it('sales summary only returns data for the active branch', function () {
    $branch1 = Branch::create(['company_id' => $this->company->id, 'name' => 'Branch 1', 'code' => 'B1-SR-'.uniqid()]);
    $branch2 = Branch::create(['company_id' => $this->company->id, 'name' => 'Branch 2', 'code' => 'B2-SR-'.uniqid()]);

    srMakeInvoice(['invoice_date' => '2024-10-01', 'branch_id' => $branch1->id], rate: 100);
    srMakeInvoice(['invoice_date' => '2024-10-01', 'branch_id' => $branch2->id], rate: 200);

    TenantService::setBranchId($branch1->id);

    $response = $this->getJson('/api/admin/sales-report/sales-summary?from_date=2024-10-01&to_date=2024-10-31');

    $response->assertOk();
    expect($response->json('data.summary.invoice_count'))->toBe(1);
    expect($response->json('data.summary.subtotal'))->toEqual(200.0);  // 2 * 100

    TenantService::setBranchId(null);
});
