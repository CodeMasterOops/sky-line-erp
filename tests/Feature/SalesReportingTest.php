<?php

use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use App\Models\AccountGroup;
use Laravel\Sanctum\Sanctum;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Models\ReceiptAllocation;
use App\Models\Receipt;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function rptWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

function makeRptInvoice(object $test, float $rate = 1000, string $lineType = 'taxable', float $taxAmount = 0): Invoice
{
    $invoice = Invoice::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'party_id' => $test->party->id,
        'invoice_no' => 'INV-RPT-'.uniqid(),
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'status' => StatusEnum::APPROVED->value,
        'approved_at' => now(),
        'approve_user_id' => $test->user->id,
        'create_user_id' => $test->user->id,
    ]);

    $invoice->invoiceItems()->create([
        'product_variant_id' => $test->variant->id,
        'quantity' => 1,
        'rate' => $rate,
        'tax_amount' => $taxAmount,
        'discount_amount' => 0,
        'tax_line_type' => $lineType,
    ]);

    return $invoice;
}

beforeEach(function () {
    rptWarmCache();
    TenantService::setCompanyId(null);
    TenantService::setBranchId(null);

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026-RPT', 'year_code' => '26RPT',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Report Test Co',
        'code' => 'RPTCO-'.uniqid(),
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Report Tester',
        'email' => 'rpt-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Report Customer',
        'code' => 'RPTCUST-'.uniqid(),
        'type' => PartyTypeEnum::CUSTOMER,
        'pan' => '123456789',
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Report Product',
        'code' => 'RPTPRD-'.uniqid(),
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'RPTSKU-'.uniqid(),
        'sales_price' => 1000,
        'is_default' => true,
    ]);

    $group = AccountGroup::create([
        'company_id' => $this->company->id, 'name' => 'RPT GL', 'code' => 'RPTGL-'.uniqid(),
    ]);
    $arAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => $group->id,
        'name' => 'AR RPT', 'code' => 'AR-RPT-'.uniqid(), 'is_active' => true,
    ]);
    $salesAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => $group->id,
        'name' => 'Sales RPT', 'code' => 'SAL-RPT-'.uniqid(), 'is_active' => true,
    ]);
    $this->cashAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => $group->id,
        'name' => 'Cash RPT', 'code' => 'CASH-RPT-'.uniqid(), 'is_active' => true,
    ]);
    AccountSetting::create([
        'company_id' => $this->company->id,
        'ar_account_id' => $arAccount->id,
        'sales_account_id' => $salesAccount->id,
    ]);

    TenantService::setCompanyId($this->company->id);
    Sanctum::actingAs($this->user, ['*'], 'admin');
});

// Phase 6c — Aging report

it('aging report returns rows sorted by days overdue with bucket totals', function () {
    $invoice = makeRptInvoice($this, 2000);
    // Backdate due_date so it shows as overdue
    $invoice->update(['due_date' => now()->subDays(45)->toDateString()]);

    $response = $this->getJson('/api/admin/sales-report/aging');

    $response->assertSuccessful();
    $data = $response->json('data');

    expect($data['rows'])->toHaveCount(1)
        ->and((float) $data['rows'][0]['outstanding'])->toEqual(2000.0)
        ->and($data['rows'][0]['bucket'])->toBe('31_60')
        ->and((float) $data['buckets']['31_60'])->toEqual(2000.0)
        ->and((float) $data['buckets']['total'])->toEqual(2000.0);
});

it('aging report excludes fully paid invoices', function () {
    $invoice = makeRptInvoice($this, 1000);

    $receipt = Receipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'receipt_no' => 'RCP-AGE-'.uniqid(),
        'receipt_date' => now()->toDateString(),
        'payment_method' => 'cash',
        'account_id' => $this->cashAccount->id,
        'status' => StatusEnum::APPROVED->value,
        'create_user_id' => $this->user->id,
    ]);

    ReceiptAllocation::create([
        'receipt_id' => $receipt->id,
        'invoice_id' => $invoice->id,
        'amount' => 1000,
    ]);

    $response = $this->getJson('/api/admin/sales-report/aging');

    $response->assertSuccessful();
    expect($response->json('data.rows'))->toHaveCount(0);
});

// Phase 6d — Party statement

it('party statement returns chronological debit/credit entries with running balance', function () {
    $invoice = makeRptInvoice($this, 3000);

    $receipt = Receipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'receipt_no' => 'RCP-STMT-'.uniqid(),
        'receipt_date' => now()->toDateString(),
        'payment_method' => 'cash',
        'account_id' => $this->cashAccount->id,
        'status' => StatusEnum::APPROVED->value,
        'create_user_id' => $this->user->id,
    ]);

    ReceiptAllocation::create([
        'receipt_id' => $receipt->id,
        'invoice_id' => $invoice->id,
        'amount' => 1500,
    ]);

    $response = $this->getJson('/api/admin/sales-report/party-statement?party_id='.$this->party->id);

    $response->assertSuccessful();
    $data = $response->json('data');

    expect($data['rows'])->toHaveCount(2)
        ->and((float) $data['summary']['total_invoiced'])->toEqual(3000.0)
        ->and((float) $data['summary']['total_received'])->toEqual(1500.0)
        ->and((float) $data['summary']['closing_balance'])->toEqual(1500.0);
});

it('party statement requires party_id', function () {
    $response = $this->getJson('/api/admin/sales-report/party-statement');

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['party_id']);
});

// Phase 7a — VAT Register

it('vat register separates taxable and exempt line items', function () {
    // Taxable invoice: rate 1000 + 130 VAT
    makeRptInvoice($this, 1000, 'taxable', 130);
    // Exempt invoice
    makeRptInvoice($this, 500, 'exempt', 0);

    $response = $this->getJson('/api/admin/sales-report/vat-register');

    $response->assertSuccessful();
    $data = $response->json('data');

    expect($data['taxable_sales'])->toHaveCount(1)
        ->and($data['exempt_sales'])->toHaveCount(1)
        ->and((float) $data['totals']['taxable'])->toEqual(1000.0)
        ->and((float) $data['totals']['vat'])->toEqual(130.0)
        ->and((float) $data['totals']['exempt'])->toEqual(500.0);
});

it('vat register includes zero rated sales separately', function () {
    makeRptInvoice($this, 800, 'zero_rated', 0);

    $response = $this->getJson('/api/admin/sales-report/vat-register');

    $response->assertSuccessful();
    $data = $response->json('data');

    expect($data['zero_rated_sales'])->toHaveCount(1)
        ->and((float) $data['totals']['zero_rated'])->toEqual(800.0);
});

// Phase 7b — TDS Register

it('tds register returns deductions grouped by party', function () {
    $deduction = \App\Models\TdsDeduction::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'deductible_type' => Invoice::class,
        'deductible_id' => 1,
        'party_id' => $this->party->id,
        'tds_category' => \App\Enums\TdsCategoryEnum::SERVICE_VAT_BILL->value,
        'base_amount' => 10000,
        'tds_rate' => 5,
        'tds_amount' => 500,
        'period_month' => (int) now()->format('n'),
    ]);

    $response = $this->getJson('/api/admin/sales-report/tds-register');

    $response->assertSuccessful();
    $data = $response->json('data');

    expect($data['rows'])->toHaveCount(1)
        ->and((float) $data['summary']['total_tds_amount'])->toEqual(500.0)
        ->and((float) $data['summary']['total_base_amount'])->toEqual(10000.0);
});

// Phase 7c — Customer Outstanding

it('outstanding report shows net outstanding per customer', function () {
    $invoice = makeRptInvoice($this, 5000);

    $receipt = Receipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'receipt_no' => 'RCP-OUT-'.uniqid(),
        'receipt_date' => now()->toDateString(),
        'payment_method' => 'cash',
        'account_id' => $this->cashAccount->id,
        'status' => StatusEnum::APPROVED->value,
        'create_user_id' => $this->user->id,
    ]);

    ReceiptAllocation::create([
        'receipt_id' => $receipt->id,
        'invoice_id' => $invoice->id,
        'amount' => 2000,
    ]);

    $response = $this->getJson('/api/admin/sales-report/outstanding');

    $response->assertSuccessful();
    $data = $response->json('data');

    expect($data['rows'])->toHaveCount(1)
        ->and($data['rows'][0]['party_name'])->toBe($this->party->name)
        ->and((float) $data['rows'][0]['total_invoiced'])->toEqual(5000.0)
        ->and((float) $data['rows'][0]['total_received'])->toEqual(2000.0)
        ->and((float) $data['rows'][0]['net_outstanding'])->toEqual(3000.0)
        ->and((float) $data['summary']['net_outstanding'])->toEqual(3000.0);
});

it('outstanding report excludes customers with zero outstanding', function () {
    $invoice = makeRptInvoice($this, 1000);

    $receipt = Receipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'receipt_no' => 'RCP-ZOUT-'.uniqid(),
        'receipt_date' => now()->toDateString(),
        'payment_method' => 'cash',
        'account_id' => $this->cashAccount->id,
        'status' => StatusEnum::APPROVED->value,
        'create_user_id' => $this->user->id,
    ]);

    ReceiptAllocation::create([
        'receipt_id' => $receipt->id,
        'invoice_id' => $invoice->id,
        'amount' => 1000,
    ]);

    $response = $this->getJson('/api/admin/sales-report/outstanding');

    $response->assertSuccessful();
    expect($response->json('data.rows'))->toHaveCount(0);
});
