<?php

use App\Models\Unit;
use App\Models\User;
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

function stockSearchWarmCache(): void
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
    stockSearchWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Stock Search Co',
        'code' => 'STKS',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Stock Tester',
        'email' => 'stock-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->warehouseA = Warehouse::create(['company_id' => $this->company->id, 'name' => 'Main', 'code' => 'W1']);
    $this->warehouseB = Warehouse::create(['company_id' => $this->company->id, 'name' => 'Backup', 'code' => 'W2']);

    $this->category = ProductCategory::create(['company_id' => $this->company->id, 'name' => 'Materials']);
    $this->unit = Unit::create(['company_id' => $this->company->id, 'name' => 'Kg', 'code' => 'KG']);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

function makeStockVariant(object $test, string $sku): ProductVariant
{
    $product = Product::create([
        'company_id' => $test->company->id,
        'product_category_id' => $test->category->id,
        'product_type' => ProductTypeEnum::PRODUCT,
        'name' => 'Searchable Steel '.$sku,
        'code' => 'STEEL-'.uniqid(),
        'unit_id' => $test->unit->id,
    ]);

    return ProductVariant::create([
        'company_id' => $test->company->id,
        'product_id' => $product->id,
        'sku' => $sku,
        'sales_price' => 10,
        'purchase_price' => 8,
        'is_default' => true,
    ]);
}

it('omits the stock field when with_stock is not requested', function () {
    $variant = makeStockVariant($this, 'NOSTOCKFLAG');
    Stock::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $variant->id,
        'warehouse_id' => $this->warehouseA->id,
        'quantity' => 7,
    ]);

    $response = $this->getJson('/api/admin/product/variant/search?q=Searchable');

    $response->assertSuccessful();
    $row = collect($response->json('data'))->firstWhere('sku', 'NOSTOCKFLAG');
    expect($row)->not->toHaveKey('stock');
});

it('returns the summed stock across warehouses when with_stock is requested', function () {
    $variant = makeStockVariant($this, 'INSTOCK');
    Stock::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $variant->id,
        'warehouse_id' => $this->warehouseA->id,
        'quantity' => 6,
    ]);
    Stock::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $variant->id,
        'warehouse_id' => $this->warehouseB->id,
        'quantity' => 4.5,
    ]);

    $response = $this->getJson('/api/admin/product/variant/search?q=Searchable&with_stock=1');

    $response->assertSuccessful();
    $row = collect($response->json('data'))->firstWhere('sku', 'INSTOCK');
    expect($row)->toHaveKey('stock');
    expect($row['stock'])->toBe(10.5);
});

it('returns zero stock when the variant has no stock rows', function () {
    makeStockVariant($this, 'ZEROSTOCK');

    $response = $this->getJson('/api/admin/product/variant/search?q=Searchable&with_stock=1');

    $response->assertSuccessful();
    $row = collect($response->json('data'))->firstWhere('sku', 'ZEROSTOCK');
    expect($row)->toHaveKey('stock');
    expect((float) $row['stock'])->toBe(0.0);
});
