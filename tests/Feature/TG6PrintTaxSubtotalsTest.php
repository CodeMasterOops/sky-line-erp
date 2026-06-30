<?php

use App\Models\Tax;
use App\Models\User;
use App\Models\Party;
use App\Models\Company;
use App\Models\Product;
use App\Models\TaxGroup;
use App\Enums\StatusEnum;
use App\Models\Warehouse;
use App\Enums\TaxTypeEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Database\Seeders\TaxSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function tg6WarmCache(): void
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
    tg6WarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2081/82',
        'year_code' => '8182',
        'start_date' => '2024-07-16',
        'end_date' => '2025-07-15',
    ]);

    $this->company = Company::create([
        'company_name' => 'TG6 Test Co',
        'code' => 'TG6TC',
        'fiscal_year_id' => $this->fiscalYear->id,
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'TG6 Tester',
        'email' => 'tg6test-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->customer = Party::create([
        'company_id' => $this->company->id,
        'name' => 'TG6 Customer',
        'code' => 'CUST-TG6',
        'type' => PartyTypeEnum::CUSTOMER,
    ]);

    $this->supplier = Party::create([
        'company_id' => $this->company->id,
        'name' => 'TG6 Supplier',
        'code' => 'SUP-TG6',
        'type' => PartyTypeEnum::SUPPLIER,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'TG6 Warehouse',
        'code' => 'TG6-W',
    ]);

    TaxSeeder::seedForCompany($this->company->id);
    TaxSeeder::seedGroupsForCompany($this->company->id);

    $this->vatGroup = TaxGroup::where('company_id', $this->company->id)->where('name', 'VAT 13%')->first();
    $this->vatTax = Tax::where('company_id', $this->company->id)->where('type', TaxTypeEnum::VAT_STANDARD)->first();

    $product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'TG6 Widget',
        'code' => 'TG6-'.uniqid(),
        'has_variants' => false,
        'is_purchasable' => true,
        'is_saleable' => true,
        'product_type' => 'product',
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'variant_name' => 'TG6 Widget',
        'sku' => 'TG6-SKU-'.uniqid(),
        'purchase_price' => 1000,
        'sales_price' => 1200,
        'is_default' => true,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

// ────────────────────────────────────────────
// Invoice
// ────────────────────────────────────────────

it('invoice item resource exposes tax_group with name when tax_group_id is set', function () {
    $response = $this->postJson('/api/admin/invoice', [
        'invoice_date' => '2024-08-01',
        'party_id' => $this->customer->id,
        'status' => StatusEnum::DRAFT->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 1,
            'rate' => 1000,
            'tax_id' => null,
            'tax_group_id' => $this->vatGroup->id,
            'tax_amount' => 130,
            'discount_amount' => 0,
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
        ]],
    ]);

    $response->assertCreated();
    $id = $response->json('data.id');

    $show = $this->getJson("/api/admin/invoice/{$id}");
    $show->assertOk();

    expect($show->json('data.items.0.tax_group_id'))->toBe($this->vatGroup->id)
        ->and($show->json('data.items.0.tax_group.name'))->toBe('VAT 13%');
});

it('invoice item resource returns null tax_group when only individual tax is set', function () {
    $response = $this->postJson('/api/admin/invoice', [
        'invoice_date' => '2024-08-01',
        'party_id' => $this->customer->id,
        'status' => StatusEnum::DRAFT->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 1,
            'rate' => 1000,
            'tax_id' => $this->vatTax->id,
            'tax_group_id' => null,
            'tax_amount' => 130,
            'discount_amount' => 0,
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
        ]],
    ]);

    $response->assertCreated();
    $id = $response->json('data.id');

    $show = $this->getJson("/api/admin/invoice/{$id}");
    $show->assertOk();

    expect($show->json('data.items.0.tax_group_id'))->toBeNull()
        ->and($show->json('data.items.0.tax_group'))->toBeNull();
});

// ────────────────────────────────────────────
// Credit Note
// ────────────────────────────────────────────

it('credit note item resource exposes tax_group with name when tax_group_id is set', function () {
    $response = $this->postJson('/api/admin/credit-note', [
        'credit_note_date' => '2024-08-01',
        'party_id' => $this->customer->id,
        'status' => StatusEnum::DRAFT->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 1,
            'rate' => 1000,
            'tax_id' => null,
            'tax_group_id' => $this->vatGroup->id,
            'tax_amount' => 130,
            'discount_amount' => 0,
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
        ]],
    ]);

    $response->assertCreated();
    $id = $response->json('data.id');

    $show = $this->getJson("/api/admin/credit-note/{$id}");
    $show->assertOk();

    expect($show->json('data.items.0.tax_group_id'))->toBe($this->vatGroup->id)
        ->and($show->json('data.items.0.tax_group.name'))->toBe('VAT 13%');
});

// ────────────────────────────────────────────
// Debit Note
// ────────────────────────────────────────────

it('debit note item resource exposes tax_group with name when tax_group_id is set', function () {
    $response = $this->postJson('/api/admin/debit-note', [
        'debit_note_date' => '2024-08-01',
        'party_id' => $this->supplier->id,
        'status' => StatusEnum::DRAFT->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 1,
            'rate' => 1000,
            'tax_id' => null,
            'tax_group_id' => $this->vatGroup->id,
            'tax_amount' => 130,
            'discount_amount' => 0,
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
        ]],
    ]);

    $response->assertCreated();
    $id = $response->json('data.id');

    $show = $this->getJson("/api/admin/debit-note/{$id}");
    $show->assertOk();

    expect($show->json('data.items.0.tax_group_id'))->toBe($this->vatGroup->id)
        ->and($show->json('data.items.0.tax_group.name'))->toBe('VAT 13%');
});

it('debit note item resource returns null tax_group when only individual tax is set', function () {
    $response = $this->postJson('/api/admin/debit-note', [
        'debit_note_date' => '2024-08-01',
        'party_id' => $this->supplier->id,
        'status' => StatusEnum::DRAFT->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 1,
            'rate' => 1000,
            'tax_id' => $this->vatTax->id,
            'tax_group_id' => null,
            'tax_amount' => 130,
            'discount_amount' => 0,
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
        ]],
    ]);

    $response->assertCreated();
    $id = $response->json('data.id');

    $show = $this->getJson("/api/admin/debit-note/{$id}");
    $show->assertOk();

    expect($show->json('data.items.0.tax_group_id'))->toBeNull()
        ->and($show->json('data.items.0.tax_group'))->toBeNull();
});
