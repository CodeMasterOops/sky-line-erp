<?php

use App\Models\Bill;
use App\Models\User;
use App\Models\Party;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\BillItem;
use App\Models\TaxGroup;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\InvoiceItem;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Database\Seeders\TaxSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function tg7WarmCache(): void
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
    tg7WarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2081/82',
        'year_code' => '8182',
        'start_date' => '2024-07-16',
        'end_date' => '2025-07-15',
        'is_current' => true,
    ]);

    $this->company = Company::create([
        'company_name' => 'TG7 Test Co',
        'code' => 'TG7TC',
        'fiscal_year_id' => $this->fiscalYear->id,
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'TG7 Tester',
        'email' => 'tg7test-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->customer = Party::create([
        'company_id' => $this->company->id,
        'name' => 'TG7 Customer',
        'code' => 'CUST-TG7',
        'type' => PartyTypeEnum::CUSTOMER,
        'pan' => '111222333',
    ]);

    $this->supplier = Party::create([
        'company_id' => $this->company->id,
        'name' => 'TG7 Supplier',
        'code' => 'SUP-TG7',
        'type' => PartyTypeEnum::SUPPLIER,
        'pan' => '444555666',
    ]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'TG7 Widget',
        'code' => 'TG7-'.uniqid(),
        'has_variants' => false,
        'is_purchasable' => true,
        'is_saleable' => true,
        'product_type' => 'product',
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'variant_name' => 'TG7 Widget',
        'sku' => 'TG7-SKU-'.uniqid(),
        'purchase_price' => 1000,
        'sales_price' => 1200,
        'is_default' => true,
    ]);

    TaxSeeder::seedForCompany($this->company->id);
    TaxSeeder::seedGroupsForCompany($this->company->id);

    $this->vatGroup = TaxGroup::where('company_id', $this->company->id)->where('name', 'VAT 13%')->first();

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

// ────────────────────────────────────────────
// VAT Sales Register
// ────────────────────────────────────────────

it('vat sales register includes tax_group_name when invoice item uses a tax group', function () {
    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'invoice_no' => 'INV-TG7-001',
        'invoice_date' => '2024-08-01',
        'party_id' => $this->customer->id,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
        'approved_at' => now(),
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => null,
        'quantity' => 1,
        'rate' => 1000,
        'discount_amount' => 0,
        'tax_group_id' => $this->vatGroup->id,
        'tax_amount' => 130,
        'tax_line_type' => 'taxable',
    ]);

    // Bypass the hour-long cache for this test
    Cache::flush();

    $response = $this->getJson('/api/admin/account-report/vat-sales-register?start_date=2024-08-01&end_date=2024-08-01');

    $response->assertOk();
    $rows = $response->json('data.rows');
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['tax_group_name'])->toBe('VAT 13%');
});

it('vat sales register returns null tax_group_name when no tax group is used', function () {
    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'invoice_no' => 'INV-TG7-002',
        'invoice_date' => '2024-08-01',
        'party_id' => $this->customer->id,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
        'approved_at' => now(),
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => null,
        'quantity' => 1,
        'rate' => 1000,
        'discount_amount' => 0,
        'tax_amount' => 130,
        'tax_line_type' => 'taxable',
    ]);

    Cache::flush();

    $response = $this->getJson('/api/admin/account-report/vat-sales-register?start_date=2024-08-01&end_date=2024-08-01');

    $response->assertOk();
    $rows = $response->json('data.rows');
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['tax_group_name'])->toBeNull();
});

// ────────────────────────────────────────────
// VAT Purchase Register
// ────────────────────────────────────────────

it('vat purchase register includes tax_group_name when bill item uses a tax group', function () {
    $bill = Bill::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'bill_no' => 'BILL-TG7-001',
        'bill_date' => '2024-08-01',
        'party_id' => $this->supplier->id,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
        'approved_at' => now(),
    ]);

    BillItem::create([
        'bill_id' => $bill->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => null,
        'quantity' => 1,
        'rate' => 1000,
        'discount_amount' => 0,
        'tax_group_id' => $this->vatGroup->id,
        'tax_amount' => 130,
        'tax_line_type' => 'taxable',
    ]);

    Cache::flush();

    $response = $this->getJson('/api/admin/account-report/vat-purchase-register?start_date=2024-08-01&end_date=2024-08-01');

    $response->assertOk();
    $rows = $response->json('data.rows');
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['tax_group_name'])->toBe('VAT 13%');
});

it('vat purchase register returns null tax_group_name when no tax group is used', function () {
    $bill = Bill::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'bill_no' => 'BILL-TG7-002',
        'bill_date' => '2024-08-01',
        'party_id' => $this->supplier->id,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
        'approved_at' => now(),
    ]);

    BillItem::create([
        'bill_id' => $bill->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => null,
        'quantity' => 1,
        'rate' => 1000,
        'discount_amount' => 0,
        'tax_amount' => 130,
        'tax_line_type' => 'taxable',
    ]);

    Cache::flush();

    $response = $this->getJson('/api/admin/account-report/vat-purchase-register?start_date=2024-08-01&end_date=2024-08-01');

    $response->assertOk();
    $rows = $response->json('data.rows');
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['tax_group_name'])->toBeNull();
});
