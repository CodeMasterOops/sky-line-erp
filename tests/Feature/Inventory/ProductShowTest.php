<?php

use App\Models\Unit;
use App\Models\User;
use App\Models\Brand;
use App\Models\Stock;
use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Enums\ProductTypeEnum;
use App\Models\ProductVariant;
use App\Models\ProductCategory;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function productShowWarmCache(): void
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
    productShowWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Show Test Co',
        'code' => 'STC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Show Tester',
        'email' => 'show-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->unit = Unit::create(['company_id' => $this->company->id, 'name' => 'Kg', 'code' => 'KG']);
    $this->brand = Brand::create(['company_id' => $this->company->id, 'name' => 'Acme', 'code' => 'ACME']);
    $this->category = ProductCategory::create(['company_id' => $this->company->id, 'name' => 'Materials']);
    $this->warehouse = Warehouse::create(['company_id' => $this->company->id, 'name' => 'Main', 'code' => 'W1']);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

it('returns product details with all relations on show', function () {
    $product = Product::create([
        'company_id' => $this->company->id,
        'product_type' => ProductTypeEnum::PRODUCT->value,
        'name' => 'Test Widget',
        'code' => 'TW-001',
        'unit_id' => $this->unit->id,
        'brand_id' => $this->brand->id,
        'product_category_id' => $this->category->id,
        'has_variants' => false,
    ]);

    ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'sku' => 'TW-SKU-001',
        'sales_price' => 200,
        'purchase_price' => 100,
        'is_default' => true,
    ]);

    $response = $this->getJson("/api/admin/product/{$product->id}");

    $response->assertOk();
    $response->assertJsonPath('data.id', $product->id);
    $response->assertJsonPath('data.name', 'Test Widget');
    $response->assertJsonPath('data.code', 'TW-001');
    $response->assertJsonPath('data.brand', 'Acme');
    $response->assertJsonPath('data.unit', 'Kg');
    $response->assertJsonPath('data.category', 'Materials');
    $response->assertJsonStructure([
        'data' => [
            'id', 'name', 'code', 'brand', 'unit', 'category', 'category_path',
            'has_variants', 'variants',
        ],
    ]);
});

it('returns variant options when product has variants', function () {
    $product = Product::create([
        'company_id' => $this->company->id,
        'product_type' => ProductTypeEnum::PRODUCT->value,
        'name' => 'Multi-Variant Product',
        'code' => 'MVP-001',
        'has_variants' => true,
    ]);

    ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'sku' => 'MVP-RED',
        'sales_price' => 150,
        'purchase_price' => 75,
        'is_default' => true,
    ]);

    $response = $this->getJson("/api/admin/product/{$product->id}");

    $response->assertOk();
    $response->assertJsonPath('data.has_variants', true);
    $response->assertJsonCount(1, 'data.variants');
    $response->assertJsonPath('data.variants.0.sku', 'MVP-RED');
    $response->assertJsonPath('data.variants.0.sales_price', 150);
    $response->assertJsonPath('data.variants.0.purchase_price', 75);
});

it('returns stock by warehouse per variant', function () {
    $product = Product::create([
        'company_id' => $this->company->id,
        'product_type' => ProductTypeEnum::PRODUCT->value,
        'name' => 'Stocked Item',
        'code' => 'SI-001',
        'has_variants' => false,
    ]);

    $variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'sku' => 'SI-SKU',
        'sales_price' => 50,
        'purchase_price' => 25,
        'is_default' => true,
    ]);

    Stock::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $variant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 42,
        'on_hold' => 5,
    ]);

    $response = $this->getJson("/api/admin/product/{$product->id}");

    $response->assertOk();
    $response->assertJsonPath('data.total_stock', 42);
    $response->assertJsonPath('data.variants.0.total_stock', 42);
    $response->assertJsonPath('data.variants.0.stocks_by_warehouse.0.quantity', 42);
    $response->assertJsonPath('data.variants.0.stocks_by_warehouse.0.on_hold', 5);
    $response->assertJsonPath('data.variants.0.stocks_by_warehouse.0.warehouse_name', 'Main');
});

it('returns 404 for a product that does not exist', function () {
    $response = $this->getJson('/api/admin/product/99999');

    $response->assertNotFound();
});

it('returns service product with null total_stock', function () {
    $product = Product::create([
        'company_id' => $this->company->id,
        'product_type' => ProductTypeEnum::SERVICE->value,
        'name' => 'Consulting',
        'code' => 'SVC-001',
        'has_variants' => false,
    ]);

    ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'sku' => 'SVC-SKU',
        'sales_price' => 500,
        'purchase_price' => 0,
        'is_default' => true,
    ]);

    $response = $this->getJson("/api/admin/product/{$product->id}");

    $response->assertOk();
    $response->assertJsonPath('data.product_type', 'service');
    $response->assertJsonPath('data.total_stock', null);
});
