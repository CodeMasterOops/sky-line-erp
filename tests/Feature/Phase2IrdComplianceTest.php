<?php

use App\Models\Bill;
use App\Models\User;
use App\Models\Party;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\BillItem;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\InvoiceItem;
use App\Models\TdsDeduction;
use Laravel\Sanctum\Sanctum;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function irdComplianceWarmCache(): void
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
    irdComplianceWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2081-82', 'year_code' => '8182',
        'start_date' => '2024-07-17', 'end_date' => '2025-07-16', 'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'IRD Compliance Co', 'code' => 'IRDC-'.uniqid(),
        'inventory_costing_method' => 'fifo',
        'pan' => '123456789',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Tester', 'email' => 'ird-'.uniqid().'@example.com',
        'password' => bcrypt('secret'), 'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Export Customer', 'code' => 'EXP-001',
        'type' => 'customer', 'pan' => '987654321',
    ]);

    $product = Product::create([
        'company_id' => $this->company->id, 'name' => 'Widget', 'code' => 'WGT-IRD',
    ]);
    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id, 'product_id' => $product->id,
        'sku' => 'SKU-IRD-D3', 'sales_price' => 100, 'is_default' => true,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

// ── IRD-002/003: Zero-rated amounts appear in D3 CSV export ──────────────────

it('includes zero-rated sales amount as a separate column in the D3 CSV export — IRD-002', function () {
    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'invoice_no' => 'INV-ZR-001',
        'invoice_date' => now()->toDateString(),
        'party_id' => $this->party->id,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
        'approved_at' => now(),
    ]);

    // Taxable line: 1000, VAT 130
    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => null,
        'quantity' => 1, 'rate' => 1000, 'discount_amount' => 0,
        'tax_amount' => 130, 'tax_line_type' => 'taxable',
    ]);

    // Zero-rated export line: 500
    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => null,
        'quantity' => 1, 'rate' => 500, 'discount_amount' => 0,
        'tax_amount' => 0, 'tax_line_type' => 'zero_rated',
    ]);

    // Exempt line: 200
    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => null,
        'quantity' => 1, 'rate' => 200, 'discount_amount' => 0,
        'tax_amount' => 0, 'tax_line_type' => 'exempt',
    ]);

    $today = now()->toDateString();
    $response = $this->get("/api/admin/nepal/vat-d3/export-csv?type=sales&start_date={$today}&end_date={$today}");

    $response->assertOk();
    $csv = $response->streamedContent();

    // Column header must include zero-rated column
    expect($csv)->toContain('Zero-Rated Amount');
    // Data values: taxable=1000, vat=130, zero_rated=500, exempt=200
    expect($csv)->toContain('1000');
    expect($csv)->toContain('130');
    expect($csv)->toContain('500');
    expect($csv)->toContain('200');
});

it('includes zero-rated purchase amount as a separate column in the D3 CSV export — IRD-003', function () {
    $bill = Bill::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'bill_no' => 'BILL-ZR-001',
        'bill_date' => now()->toDateString(),
        'party_id' => $this->party->id,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
        'approved_at' => now(),
    ]);

    // Taxable line: 2000, VAT 260
    BillItem::create([
        'bill_id' => $bill->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => null,
        'quantity' => 1, 'rate' => 2000, 'discount_amount' => 0,
        'tax_amount' => 260, 'tax_line_type' => 'taxable',
    ]);

    // Zero-rated import line: 800
    BillItem::create([
        'bill_id' => $bill->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => null,
        'quantity' => 1, 'rate' => 800, 'discount_amount' => 0,
        'tax_amount' => 0, 'tax_line_type' => 'zero_rated',
    ]);

    $today = now()->toDateString();
    // Purchase data now lives in the D4 (purchase register) endpoint.
    $response = $this->get("/api/admin/nepal/vat-d4/export-csv?start_date={$today}&end_date={$today}");

    $response->assertOk();
    $csv = $response->streamedContent();

    expect($csv)->toContain('Zero-Rated Amount');
    expect($csv)->toContain('2000');
    expect($csv)->toContain('260');
    expect($csv)->toContain('800');
});

// ── IRD-004: TDS challan summary exposes source document reference ────────────

it('TDS challan summary includes deductible source document reference — IRD-004', function () {
    TdsDeduction::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'deductible_type' => 'App\\Models\\Payment',
        'deductible_id' => 42,
        'party_id' => $this->party->id,
        'tds_category' => 'service_vat_bill',
        'base_amount' => 10000,
        'tds_rate' => 1.5,
        'tds_amount' => 150,
        'period_month' => '2024-08',
    ]);

    $today = now()->toDateString();
    $response = $this->getJson("/api/admin/nepal/tds/summary?start_date={$today}&end_date={$today}");

    $response->assertOk();

    $row = $response->json('data.rows.0');
    expect($row)->toHaveKey('deductible_type');
    expect($row)->toHaveKey('deductible_id');
    expect($row['deductible_type'])->toBe('App\\Models\\Payment');
    expect($row['deductible_id'])->toBe(42);
    expect($row['party_pan'])->toBe('987654321');
});
