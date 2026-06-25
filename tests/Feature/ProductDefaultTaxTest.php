<?php

use App\Models\Tax;
use App\Models\Unit;
use App\Models\User;
use App\Models\Company;
use App\Models\Product;
use App\Models\TaxGroup;
use App\Enums\TaxTypeEnum;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\ProductVariant;
use App\Models\ProductCategory;
use App\Services\TenantService;
use Database\Seeders\TaxSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function pdtWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
}

function pdtBasePayload(array $overrides = []): array
{
    return array_merge([
        'product_type' => 'product',
        'name' => 'Test Product '.uniqid(),
        'code' => 'TP-'.uniqid(),
        'product_category_id' => null,
        'unit_id' => null,
        'reorder_quantity' => 1,
        'has_variants' => false,
        'variants' => [
            ['sales_price' => 100, 'purchase_price' => 60, 'is_default' => true],
        ],
    ], $overrides);
}

beforeEach(function () {
    pdtWarmCache();

    $this->company = Company::create([
        'company_name' => 'Default Tax Co',
        'code' => 'DTC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Tax Tester',
        'email' => 'pdttest-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->category = ProductCategory::create([
        'company_id' => $this->company->id,
        'name' => 'General',
    ]);

    $this->unit = Unit::create([
        'company_id' => $this->company->id,
        'name' => 'Piece',
        'code' => 'PC-'.uniqid(),
    ]);

    TaxSeeder::seedForCompany($this->company->id);
    TaxSeeder::seedGroupsForCompany($this->company->id);

    $this->vatTax = Tax::where('company_id', $this->company->id)->where('type', TaxTypeEnum::VAT_STANDARD)->first();
    $this->vatGroup = TaxGroup::where('company_id', $this->company->id)->where('name', 'VAT 13%')->first();

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

// ────────────────────────────────────────────
// Schema
// ────────────────────────────────────────────

it('products table has tax_group_id column', function () {
    expect(Schema::getColumnListing('products'))->toContain('tax_group_id');
});

// ────────────────────────────────────────────
// API — store with tax_group_id
// ────────────────────────────────────────────

it('creates a product with a tax group', function () {
    $payload = pdtBasePayload([
        'product_category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'tax_group_id' => $this->vatGroup->id,
    ]);

    $response = $this->postJson('/api/admin/product', $payload);

    $response->assertCreated();
    expect($response->json('data.tax_group_id'))->toBe($this->vatGroup->id);

    $product = Product::find($response->json('data.id'));
    expect($product->tax_group_id)->toBe($this->vatGroup->id);
});

it('creates a product with an individual tax', function () {
    $payload = pdtBasePayload([
        'product_category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'tax_id' => $this->vatTax->id,
    ]);

    $response = $this->postJson('/api/admin/product', $payload);

    $response->assertCreated();
    expect((int) $response->json('data.tax_id'))->toBe($this->vatTax->id);
});

it('rejects a product with both tax_id and tax_group_id', function () {
    $payload = pdtBasePayload([
        'product_category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'tax_id' => $this->vatTax->id,
        'tax_group_id' => $this->vatGroup->id,
    ]);

    $this->postJson('/api/admin/product', $payload)
        ->assertUnprocessable();
});

// ────────────────────────────────────────────
// Variant search — tax fields exposed
// ────────────────────────────────────────────

it('exposes tax_group_id from product in variant search results', function () {
    $product = Product::create([
        'company_id' => $this->company->id,
        'branch_id' => null,
        'product_category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'product_type' => 'product',
        'name' => 'Tax Group Product',
        'code' => 'TGP-'.uniqid(),
        'tax_group_id' => $this->vatGroup->id,
        'has_variants' => false,
        'is_saleable' => true,
        'is_purchasable' => true,
    ]);

    ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'variant_name' => 'Tax Group Product',
        'sales_price' => 100,
        'purchase_price' => 60,
        'is_default' => true,
    ]);

    $response = $this->getJson('/api/admin/product/variant/search?q=Tax+Group+Product');

    $response->assertOk();
    $hit = collect($response->json('data'))->firstWhere('id', ProductVariant::where('product_id', $product->id)->value('id'));
    expect($hit)->not->toBeNull();
    expect($hit['tax_group_id'])->toBe($this->vatGroup->id);
    expect($hit['tax_id'])->toBeNull();
});

it('exposes tax_id from product in variant search results', function () {
    $product = Product::create([
        'company_id' => $this->company->id,
        'branch_id' => null,
        'product_category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'product_type' => 'product',
        'name' => 'Tax Id Product',
        'code' => 'TIP-'.uniqid(),
        'tax_id' => $this->vatTax->id,
        'has_variants' => false,
        'is_saleable' => true,
        'is_purchasable' => true,
    ]);

    ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'variant_name' => 'Tax Id Product',
        'sales_price' => 100,
        'purchase_price' => 60,
        'is_default' => true,
    ]);

    $response = $this->getJson('/api/admin/product/variant/search?q=Tax+Id+Product');

    $response->assertOk();
    $hit = collect($response->json('data'))->firstWhere('id', ProductVariant::where('product_id', $product->id)->value('id'));
    expect($hit)->not->toBeNull();
    expect($hit['tax_id'])->toBe($this->vatTax->id);
    expect($hit['tax_group_id'])->toBeNull();
});
