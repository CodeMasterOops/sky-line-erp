<?php

use App\Models\Tax;
use App\Models\Unit;
use App\Models\User;
use App\Models\Party;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Enums\StatusEnum;
use App\Enums\TaxTypeEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\InvoiceItem;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Enums\TaxLineTypeEnum;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Invoice PDF Test Co',
        'legal_name' => 'Invoice PDF Test Co Pvt. Ltd.',
        'code' => 'IPTC',
        'pan' => '123456789',
        'address' => 'Kathmandu, Nepal',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Invoice PDF Tester',
        'email' => 'invoice-pdf-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');

    TenantService::setCompanyId($this->company->id);
});

it('downloads invoice pdf with a sanitized filename when invoice number contains slashes', function () {
    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'invoice_no' => '081/82-001',
        'invoice_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $response = $this->get("/api/admin/nepal/invoice/{$invoice->id}/pdf");

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect($response->headers->get('content-disposition'))->toContain('INV-081-82-001.pdf');
});

it('renders invoice pdf with line items and tax summary', function () {
    $party = Party::create([
        'company_id' => $this->company->id,
        'type' => PartyTypeEnum::CUSTOMER,
        'name' => 'Retail Customer',
        'code' => 'RC-1',
        'pan' => '987654321',
        'is_active' => true,
    ]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Test Widget',
        'code' => 'TW-1',
        'hsn_code' => '1234',
    ]);

    $variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'sku' => 'TW-SKU-1',
        'sales_price' => 100,
        'is_default' => true,
    ]);

    $unit = Unit::create([
        'company_id' => $this->company->id,
        'name' => 'Piece',
        'code' => 'PC',
    ]);

    $tax = Tax::create([
        'company_id' => $this->company->id,
        'name' => 'VAT 13%',
        'rate' => 13,
        'type' => TaxTypeEnum::VAT_STANDARD,
        'is_system' => false,
    ]);

    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $party->id,
        'invoice_no' => 'INV-001',
        'invoice_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
        'ird_sync_status' => 'synced',
        'ird_internal_id' => 'IRD-123',
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_variant_id' => $variant->id,
        'unit_id' => $unit->id,
        'tax_id' => $tax->id,
        'quantity' => 2,
        'rate' => 100,
        'discount_amount' => 0,
        'tax_amount' => 26,
        'tax_line_type' => TaxLineTypeEnum::TAXABLE,
    ]);

    $response = $this->get("/api/admin/nepal/invoice/{$invoice->id}/pdf");

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect(strlen($response->getContent()))->toBeGreaterThan(1000);
});

it('renders nepal invoice template with product code and invoice note', function () {
    $party = Party::create([
        'company_id' => $this->company->id,
        'type' => PartyTypeEnum::CUSTOMER,
        'name' => 'Retail Customer',
        'code' => 'RC-2',
        'is_active' => true,
    ]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Coded Widget',
        'code' => 'CW-99',
        'hsn_code' => '5678',
    ]);

    $variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'sku' => 'CW-SKU',
        'sales_price' => 50,
        'is_default' => true,
    ]);

    $unit = Unit::create([
        'company_id' => $this->company->id,
        'name' => 'Piece',
        'code' => 'PC',
    ]);

    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $party->id,
        'invoice_no' => 'INV-CODE-001',
        'invoice_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_variant_id' => $variant->id,
        'unit_id' => $unit->id,
        'quantity' => 1,
        'rate' => 50,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'tax_line_type' => TaxLineTypeEnum::EXEMPT,
    ]);

    $invoice->load([
        'party',
        'invoiceItems.productVariant.product',
        'invoiceItems.unit',
        'invoiceItems.tax',
        'fiscalYear',
    ]);

    $this->company->update([
        'invoice_note' => 'Payment due within 30 days.',
    ]);

    $html = view('pdf.nepal-invoice', [
        'invoice' => $invoice,
        'company' => $this->company->fresh(),
        'logoPath' => null,
        'invoiceDateAd' => $invoice->invoice_date instanceof \DateTimeInterface
            ? $invoice->invoice_date->format('Y-m-d')
            : (string) $invoice->invoice_date,
        'dueDateAd' => null,
        'invoiceDateBs' => '',
        'subtotal' => 50.0,
        'totalDiscount' => 0.0,
        'vatTaxableAmount' => 0.0,
        'vatAmount' => 0.0,
        'exemptAmount' => 50.0,
        'zeroRatedAmount' => 0.0,
        'grandTotal' => 50.0,
        'amountInWords' => 'Fifty only',
        'qrCode' => '',
    ])->render();

    expect($html)->toContain('CW-99');
    expect($html)->toContain('Payment due within 30 days.');
});

it('sanitizes download filenames', function () {
    expect(sanitizeDownloadFilename('INV-081/82-001.pdf'))->toBe('INV-081-82-001.pdf');
    expect(sanitizeDownloadFilename('path\\to\\file.pdf'))->toBe('path-to-file.pdf');
});
