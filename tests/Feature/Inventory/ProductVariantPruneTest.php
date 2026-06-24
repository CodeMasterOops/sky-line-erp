<?php

use App\Models\Unit;
use App\Models\User;
use App\Models\Stock;
use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
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

function pruneWarmAllTablesCache(): void
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
    pruneWarmAllTablesCache();

    $this->company = Company::create([
        'company_name' => 'Prune Test Co',
        'code' => 'PTC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Prune Tester',
        'email' => 'prune-'.uniqid().'@example.com',
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
        'code' => 'PC',
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Main',
        'code' => 'W1',
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

it('soft-deletes variants removed from the edit payload so they stop appearing in search', function () {
    $product = Product::create([
        'company_id' => $this->company->id,
        'product_category_id' => $this->category->id,
        'product_type' => ProductTypeEnum::PRODUCT,
        'name' => 'copy',
        'code' => 'PROD-PRUNE-1',
        'unit_id' => $this->unit->id,
    ]);

    $keep = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'sku' => 'KEEP-1',
        'sales_price' => 100,
        'purchase_price' => 50,
        'is_default' => true,
    ]);

    $orphan = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'sku' => 'ORPHAN-1',
        'sales_price' => 90,
        'purchase_price' => 40,
        'is_default' => false,
    ]);

    $this->putJson("/api/admin/product/{$product->id}", [
        'product_type' => ProductTypeEnum::PRODUCT->value,
        'name' => 'copy',
        'code' => 'PROD-PRUNE-1',
        'product_category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'has_variants' => false,
        'variants' => [
            [
                'id' => $keep->id,
                'sku' => 'KEEP-1',
                'sales_price' => 100,
                'purchase_price' => 50,
                'is_default' => true,
            ],
        ],
    ])->assertSuccessful();

    expect(ProductVariant::whereKey($keep->id)->exists())->toBeTrue();
    expect(ProductVariant::whereKey($orphan->id)->exists())->toBeFalse();
    expect(ProductVariant::withTrashed()->whereKey($orphan->id)->first()->trashed())->toBeTrue();
});

it('protects removed variants that still hold stock on hand', function () {
    $product = Product::create([
        'company_id' => $this->company->id,
        'product_category_id' => $this->category->id,
        'product_type' => ProductTypeEnum::PRODUCT,
        'name' => 'stocked',
        'code' => 'PROD-PRUNE-2',
        'unit_id' => $this->unit->id,
    ]);

    $keep = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'sku' => 'KEEP-2',
        'sales_price' => 100,
        'purchase_price' => 50,
        'is_default' => true,
    ]);

    $stocked = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'sku' => 'STOCKED-2',
        'sales_price' => 90,
        'purchase_price' => 40,
        'is_default' => false,
    ]);

    Stock::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $stocked->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 7,
    ]);

    $this->putJson("/api/admin/product/{$product->id}", [
        'product_type' => ProductTypeEnum::PRODUCT->value,
        'name' => 'stocked',
        'code' => 'PROD-PRUNE-2',
        'product_category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'has_variants' => false,
        'variants' => [
            [
                'id' => $keep->id,
                'sku' => 'KEEP-2',
                'sales_price' => 100,
                'purchase_price' => 50,
                'is_default' => true,
            ],
        ],
    ])->assertSuccessful();

    expect(ProductVariant::whereKey($stocked->id)->exists())->toBeTrue();
});
