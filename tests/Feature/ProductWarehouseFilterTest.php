<?php

use App\Models\User;
use App\Models\Stock;
use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use App\Enums\ProductTypeEnum;
use App\Models\ProductVariant;
use App\Models\DataTransferJob;
use App\Services\TenantService;
use App\Enums\InventoryCostingMethodEnum;
use App\Enums\DataTransfer\DataTransferStatusEnum;
use App\Enums\DataTransfer\DataTransferDirectionEnum;
use App\Enums\DataTransfer\DataTransferEntityTypeEnum;
use App\Services\DataTransfer\Export\Generators\ProductExportGenerator;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $fiscalYear->id,
        'company_name' => 'Warehouse Filter Co',
        'code' => 'WFC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Tester',
        'email' => 'wh-filter-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->warehouseA = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Main',
        'code' => 'W1',
    ]);

    $this->warehouseB = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Branch',
        'code' => 'W2',
    ]);

    $this->productBoth = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Widget Both',
        'code' => 'BOTH',
    ]);

    $this->variantBoth = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->productBoth->id,
        'sku' => 'SKU-BOTH',
        'is_default' => true,
    ]);

    Stock::withoutGlobalScopes()->create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variantBoth->id,
        'warehouse_id' => $this->warehouseA->id,
        'quantity' => 7,
        'on_hold' => 0,
    ]);

    Stock::withoutGlobalScopes()->create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variantBoth->id,
        'warehouse_id' => $this->warehouseB->id,
        'quantity' => 3,
        'on_hold' => 0,
    ]);

    $this->productAOnly = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Widget A Only',
        'code' => 'A-ONLY',
    ]);

    $this->variantAOnly = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->productAOnly->id,
        'sku' => 'SKU-A',
        'is_default' => true,
    ]);

    Stock::withoutGlobalScopes()->create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variantAOnly->id,
        'warehouse_id' => $this->warehouseA->id,
        'quantity' => 5,
        'on_hold' => 0,
    ]);

    $this->serviceProduct = Product::create([
        'company_id' => $this->company->id,
        'product_type' => ProductTypeEnum::SERVICE,
        'name' => 'Consulting',
        'code' => 'SVC-1',
    ]);

    ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->serviceProduct->id,
        'sku' => 'SKU-SVC',
        'is_default' => true,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

test('product list without warehouse filter returns total stock across all warehouses', function () {
    $response = $this->getJson('/api/admin/product?search=BOTH');

    $response->assertSuccessful();

    $row = collect($response->json('data'))->firstWhere('code', 'BOTH');
    expect($row)->not->toBeNull();
    expect($row['total_stock'])->toBe(10);
    expect($row['stock_by_warehouse'])->toHaveCount(2);
});

test('product list filtered by single warehouse scopes stock and excludes other products', function () {
    $response = $this->getJson('/api/admin/product?warehouse_ids='.$this->warehouseB->id);

    $response->assertSuccessful();

    $codes = collect($response->json('data'))->pluck('code')->all();
    expect($codes)->toContain('BOTH');
    expect($codes)->not->toContain('A-ONLY');

    $row = collect($response->json('data'))->firstWhere('code', 'BOTH');
    expect($row['total_stock'])->toBe(3);
    expect($row['stock_by_warehouse'])->toHaveCount(1);
    expect($row['stock_by_warehouse'][0]['warehouse_id'])->toBe($this->warehouseB->id);
});

test('product list filtered by multiple warehouses uses OR logic and sums selected warehouses only', function () {
    $ids = $this->warehouseA->id.','.$this->warehouseB->id;
    $response = $this->getJson('/api/admin/product?warehouse_ids='.$ids.'&limit=50');

    $response->assertSuccessful();

    $codes = collect($response->json('data'))->pluck('code')->all();
    expect($codes)->toContain('BOTH', 'A-ONLY');

    $row = collect($response->json('data'))->firstWhere('code', 'BOTH');
    expect($row['total_stock'])->toBe(10);
    expect($row['stock_by_warehouse'])->toHaveCount(2);
});

test('service products are excluded when warehouse filter is active', function () {
    $response = $this->getJson('/api/admin/product?warehouse_ids='.$this->warehouseA->id.'&limit=50');

    $response->assertSuccessful();

    $codes = collect($response->json('data'))->pluck('code')->all();
    expect($codes)->not->toContain('SVC-1');
});

test('product export respects warehouse_ids filter', function () {
    $job = DataTransferJob::create([
        'uuid' => (string) Str::uuid(),
        'company_id' => $this->company->id,
        'user_id' => $this->user->id,
        'direction' => DataTransferDirectionEnum::Export,
        'entity_type' => DataTransferEntityTypeEnum::Product,
        'status' => DataTransferStatusEnum::Processing,
        'options' => [
            'format' => 'csv',
            'filters' => [
                'warehouse_ids' => (string) $this->warehouseB->id,
            ],
        ],
        'stats' => [
            'total_rows' => 0,
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'valid' => 0,
            'invalid' => 0,
        ],
    ]);

    $path = storage_path('app/product-export-'.uniqid().'.csv');
    app(ProductExportGenerator::class)->generate($job, $path);

    $lines = array_values(array_filter(file($path) ?: [], static fn (string $line): bool => trim($line) !== ''));
    @unlink($path);

    expect($lines)->toHaveCount(2);
    expect($lines[1])->toContain('BOTH');
    expect($lines[1])->not->toContain('A-ONLY');
});

test('parseWarehouseIds accepts comma-separated and array input', function () {
    expect(Product::parseWarehouseIds('1,2,3'))->toBe([1, 2, 3]);
    expect(Product::parseWarehouseIds(['1', '2']))->toBe([1, 2]);
    expect(Product::parseWarehouseIds(''))->toBe([]);
    expect(Product::parseWarehouseIds(null))->toBe([]);
});
