<?php

use App\Models\Tax;
use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Product;
use App\Models\TaxGroup;
use App\Enums\StatusEnum;
use App\Enums\TaxTypeEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\ExpenseItem;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\QuotationItem;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Models\SalesOrderItem;
use App\Services\TenantService;
use Database\Seeders\TaxSeeder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function tg4WarmCache(): void
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
    tg4WarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2081/82',
        'year_code' => '8182',
        'start_date' => '2024-07-16',
        'end_date' => '2025-07-15',
    ]);

    $this->company = Company::create([
        'company_name' => 'TG4 Test Co',
        'code' => 'TG4TC',
        'fiscal_year_id' => $this->fiscalYear->id,
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'TG4 Tester',
        'email' => 'tg4test-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->customer = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Test Customer',
        'code' => 'CUST-TG4',
        'type' => PartyTypeEnum::CUSTOMER,
    ]);

    $this->supplier = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Test Supplier',
        'code' => 'SUP-TG4',
        'type' => PartyTypeEnum::SUPPLIER,
    ]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'TG4 Item',
        'code' => 'TG4-'.uniqid(),
        'has_variants' => false,
        'is_purchasable' => true,
        'is_saleable' => true,
        'product_type' => 'product',
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'variant_name' => 'TG4 Item',
        'purchase_price' => 1000,
        'sales_price' => 1200,
        'is_default' => true,
    ]);

    TaxSeeder::seedForCompany($this->company->id);
    TaxSeeder::seedGroupsForCompany($this->company->id);

    $this->vatGroup = TaxGroup::where('company_id', $this->company->id)->where('name', 'VAT 13%')->first();
    $this->vatTax = Tax::where('company_id', $this->company->id)->where('type', TaxTypeEnum::VAT_STANDARD)->first();

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

// ────────────────────────────────────────────
// Schema checks
// ────────────────────────────────────────────

it('quotation_items table has tax_group_id column', function () {
    expect(Schema::getColumnListing('quotation_items'))->toContain('tax_group_id');
});

it('sales_order_items table has tax_group_id column', function () {
    expect(Schema::getColumnListing('sales_order_items'))->toContain('tax_group_id');
});

it('purchase_order_items table has tax_group_id column', function () {
    expect(Schema::getColumnListing('purchase_order_items'))->toContain('tax_group_id');
});

it('expense_items table has tax_group_id column', function () {
    expect(Schema::getColumnListing('expense_items'))->toContain('tax_group_id');
});

// ────────────────────────────────────────────
// Quotation
// ────────────────────────────────────────────

it('saves tax_group_id on quotation item when provided', function () {
    $payload = [
        'quotation_date' => '2024-08-01',
        'expiry_date' => null,
        'party_id' => $this->customer->id,
        'remarks' => 'Test',
        'status' => StatusEnum::DRAFT->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => '0',
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'unit_id' => null,
            'quantity' => 1,
            'rate' => 1000,
            'tax_id' => null,
            'tax_group_id' => $this->vatGroup->id,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
        ]],
    ];

    $response = $this->postJson('/api/admin/quotation', $payload);

    $response->assertCreated();
    $item = QuotationItem::where('quotation_id', $response->json('data.id'))->first();
    expect($item->tax_group_id)->toBe($this->vatGroup->id);
});

it('exposes tax_group_id in quotation item resource', function () {
    $payload = [
        'quotation_date' => '2024-08-01',
        'party_id' => $this->customer->id,
        'status' => StatusEnum::DRAFT->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => '0',
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'unit_id' => null,
            'quantity' => 1,
            'rate' => 1000,
            'tax_id' => null,
            'tax_group_id' => $this->vatGroup->id,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
        ]],
    ];

    $response = $this->postJson('/api/admin/quotation', $payload);

    $response->assertCreated();
    $id = $response->json('data.id');
    $show = $this->getJson("/api/admin/quotation/{$id}");
    $show->assertOk();
    expect($show->json('data.items.0.tax_group_id'))->toBe($this->vatGroup->id);
});

it('validates tax_group_id must exist in tax_groups for quotation', function () {
    $payload = [
        'quotation_date' => '2024-08-01',
        'party_id' => $this->customer->id,
        'status' => StatusEnum::DRAFT->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => '0',
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'unit_id' => null,
            'quantity' => 1,
            'rate' => 1000,
            'tax_group_id' => 99999,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
        ]],
    ];

    $this->postJson('/api/admin/quotation', $payload)->assertUnprocessable();
});

// ────────────────────────────────────────────
// Sales Order
// ────────────────────────────────────────────

it('saves tax_group_id on sales order item when provided', function () {
    $payload = [
        'order_date' => '2024-08-01',
        'party_id' => $this->customer->id,
        'status' => StatusEnum::DRAFT->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => '0',
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'unit_id' => null,
            'quantity' => 1,
            'rate' => 1000,
            'tax_id' => null,
            'tax_group_id' => $this->vatGroup->id,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
        ]],
    ];

    $response = $this->postJson('/api/admin/sales-order', $payload);

    $response->assertCreated();
    $item = SalesOrderItem::where('sales_order_id', $response->json('data.id'))->first();
    expect($item->tax_group_id)->toBe($this->vatGroup->id);
});

it('exposes tax_group_id in sales order item resource', function () {
    $payload = [
        'order_date' => '2024-08-01',
        'party_id' => $this->customer->id,
        'status' => StatusEnum::DRAFT->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => '0',
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'unit_id' => null,
            'quantity' => 1,
            'rate' => 1000,
            'tax_id' => null,
            'tax_group_id' => $this->vatGroup->id,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
        ]],
    ];

    $response = $this->postJson('/api/admin/sales-order', $payload);
    $response->assertCreated();
    $id = $response->json('data.id');
    $show = $this->getJson("/api/admin/sales-order/{$id}");
    $show->assertOk();
    expect($show->json('data.items.0.tax_group_id'))->toBe($this->vatGroup->id);
});

// ────────────────────────────────────────────
// Purchase Order
// ────────────────────────────────────────────

it('saves tax_group_id on purchase order item when provided', function () {
    $payload = [
        'order_date' => '2024-08-01',
        'party_id' => $this->supplier->id,
        'status' => StatusEnum::DRAFT->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => '0',
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'unit_id' => null,
            'quantity' => 1,
            'rate' => 1000,
            'tax_id' => null,
            'tax_group_id' => $this->vatGroup->id,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
        ]],
    ];

    $response = $this->postJson('/api/admin/purchase-order', $payload);

    $response->assertCreated();
    $item = PurchaseOrderItem::where('purchase_order_id', $response->json('data.id'))->first();
    expect($item->tax_group_id)->toBe($this->vatGroup->id);
});

it('exposes tax_group_id in purchase order item resource', function () {
    $payload = [
        'order_date' => '2024-08-01',
        'party_id' => $this->supplier->id,
        'status' => StatusEnum::DRAFT->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => '0',
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'unit_id' => null,
            'quantity' => 1,
            'rate' => 1000,
            'tax_id' => null,
            'tax_group_id' => $this->vatGroup->id,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
        ]],
    ];

    $response = $this->postJson('/api/admin/purchase-order', $payload);
    $response->assertCreated();
    $id = $response->json('data.id');
    $show = $this->getJson("/api/admin/purchase-order/{$id}");
    $show->assertOk();
    expect($show->json('data.items.0.tax_group_id'))->toBe($this->vatGroup->id);
});

// ────────────────────────────────────────────
// Expense
// ────────────────────────────────────────────

it('saves tax_group_id on expense item and recalculates tax_amount', function () {
    $account = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Office Supplies',
        'code' => 'OFF-TG4',
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'purchase_account_id' => $account->id,
        'supplier_account_id' => $account->id,
        'vat_account_id' => $account->id,
    ]);

    $payload = [
        'date' => '2024-08-01',
        'party_id' => $this->supplier->id,
        'status' => StatusEnum::DRAFT->value,
        'items' => [[
            'account_id' => $account->id,
            'amount' => 1000,
            'tax_id' => null,
            'tax_group_id' => $this->vatGroup->id,
            'tax_amount' => 0,
            'discount_amount' => 0,
        ]],
    ];

    $response = $this->postJson('/api/admin/expense', $payload);

    $response->assertCreated();
    $item = ExpenseItem::where('expense_id', $response->json('data.id'))->first();
    expect($item->tax_group_id)->toBe($this->vatGroup->id);
    expect((float) $item->tax_amount)->toBe(130.0);
});

it('validates tax_group_id must exist in tax_groups for expense', function () {
    $account = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Office Supplies 2',
        'code' => 'OFF2-TG4',
    ]);

    $payload = [
        'date' => '2024-08-01',
        'status' => StatusEnum::DRAFT->value,
        'items' => [[
            'account_id' => $account->id,
            'amount' => 1000,
            'tax_group_id' => 99999,
            'tax_amount' => 0,
            'discount_amount' => 0,
        ]],
    ];

    $this->postJson('/api/admin/expense', $payload)->assertUnprocessable();
});
