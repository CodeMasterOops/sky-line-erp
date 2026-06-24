<?php

use App\Models\Unit;
use App\Models\User;
use App\Models\Stock;
use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
use App\Enums\UserTypeEnum;
use App\Enums\ChangeTypeEnum;
use App\Models\StockMovement;
use App\Enums\ProductTypeEnum;
use App\Models\ProductVariant;
use App\Services\TenantService;
use App\Enums\StockDirectionEnum;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function pruneCmdWarmAllTablesCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
}

function pruneCmdVariant(Product $product, string $sku, bool $isDefault = false): ProductVariant
{
    return ProductVariant::create([
        'company_id' => $product->company_id,
        'product_id' => $product->id,
        'sku' => $sku,
        'sales_price' => 100,
        'purchase_price' => 50,
        'is_default' => $isDefault,
    ]);
}

beforeEach(function () {
    pruneCmdWarmAllTablesCache();

    $this->company = Company::create([
        'company_name' => 'Prune Cmd Co',
        'code' => 'PCC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Cmd Tester',
        'email' => 'cmd-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
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

    TenantService::setCompanyId($this->company->id);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'product_type' => ProductTypeEnum::PRODUCT,
        'name' => 'copy',
        'code' => 'PROD-CMD-1',
        'unit_id' => $this->unit->id,
    ]);

    $this->stocked = pruneCmdVariant($this->product, 'STOCKED', true);
    $this->withHistory = pruneCmdVariant($this->product, 'HISTORY');
    $this->orphanA = pruneCmdVariant($this->product, 'ORPHAN-A');
    $this->orphanB = pruneCmdVariant($this->product, 'ORPHAN-B');

    Stock::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->stocked->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 5,
    ]);

    StockMovement::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->withHistory->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 3,
        'type' => ChangeTypeEnum::PURCHASE,
        'direction' => StockDirectionEnum::IN,
    ]);
});

it('reports orphans without deleting on a dry run', function () {
    $this->artisan('products:prune-orphan-variants')
        ->assertSuccessful();

    expect(ProductVariant::whereKey($this->orphanA->id)->exists())->toBeTrue();
    expect(ProductVariant::whereKey($this->orphanB->id)->exists())->toBeTrue();
});

it('soft-deletes only unused zero-stock variants when applied', function () {
    $this->artisan('products:prune-orphan-variants --apply')
        ->assertSuccessful();

    expect(ProductVariant::whereKey($this->orphanA->id)->exists())->toBeFalse();
    expect(ProductVariant::whereKey($this->orphanB->id)->exists())->toBeFalse();

    expect(ProductVariant::whereKey($this->stocked->id)->exists())->toBeTrue();
    expect(ProductVariant::whereKey($this->withHistory->id)->exists())->toBeTrue();
});

it('never deletes the last remaining variant of a product', function () {
    $product = Product::create([
        'company_id' => $this->company->id,
        'product_type' => ProductTypeEnum::PRODUCT,
        'name' => 'all empty',
        'code' => 'PROD-CMD-2',
        'unit_id' => $this->unit->id,
    ]);

    $a = pruneCmdVariant($product, 'EMPTY-A', true);
    $b = pruneCmdVariant($product, 'EMPTY-B');

    $this->artisan('products:prune-orphan-variants --apply')
        ->assertSuccessful();

    $remaining = ProductVariant::where('product_id', $product->id)->count();
    expect($remaining)->toBe(1);
    expect(ProductVariant::whereKey($a->id)->exists())->toBeTrue();
    expect(ProductVariant::whereKey($b->id)->exists())->toBeFalse();
});
