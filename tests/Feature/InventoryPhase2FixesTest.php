<?php

use App\Models\User;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Product;
use App\Enums\StatusEnum;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\SerialNumber;
use Laravel\Sanctum\Sanctum;
use App\Models\StockTransfer;
use App\Enums\ProductTypeEnum;
use App\Models\ProductVariant;
use App\Services\TenantService;
use App\Enums\StockDirectionEnum;
use App\Models\OpeningStockEntry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Phase2 Test Co',
        'code' => 'P2TC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'name' => 'Main Branch',
        'code' => 'BR1',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Admin',
        'email' => 'p2-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Main WH',
        'code' => 'WH1',
    ]);

    $this->warehouseB = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Secondary WH',
        'code' => 'WH2',
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Phase2 Widget',
        'code' => 'WIDGET-P2',
        'product_type' => ProductTypeEnum::PRODUCT,
    ]);

    $this->variantA = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'SKU-P2-A',
        'is_default' => true,
    ]);

    $this->variantB = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'SKU-P2-B',
        'is_default' => false,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
    TenantService::setBranchId($this->branch->id);
});

// ─── BL-005: Duplicate variant in stock adjustment ───────────────────────────

it('rejects duplicate product_variant_id in same stock adjustment', function () {
    $payload = [
        'date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'status' => StatusEnum::DRAFT->value,
        'items' => [
            ['product_variant_id' => $this->variantA->id, 'direction' => StockDirectionEnum::IN->value, 'quantity' => 5, 'unit_cost' => 10],
            ['product_variant_id' => $this->variantA->id, 'direction' => StockDirectionEnum::IN->value, 'quantity' => 3, 'unit_cost' => 10],
        ],
    ];

    $this->postJson('/api/admin/stock-adjustment', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items.1.product_variant_id']);
});

it('accepts two different variants in the same stock adjustment', function () {
    $payload = [
        'date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'status' => StatusEnum::DRAFT->value,
        'items' => [
            ['product_variant_id' => $this->variantA->id, 'direction' => StockDirectionEnum::IN->value, 'quantity' => 5, 'unit_cost' => 10],
            ['product_variant_id' => $this->variantB->id, 'direction' => StockDirectionEnum::IN->value, 'quantity' => 3, 'unit_cost' => 10],
        ],
    ];

    $this->postJson('/api/admin/stock-adjustment', $payload)
        ->assertCreated();
});

// ─── BL-005: Duplicate variant in opening stock entry ────────────────────────

it('rejects duplicate product_variant_id in same opening stock entry', function () {
    $payload = [
        'date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'status' => StatusEnum::DRAFT->value,
        'items' => [
            ['product_variant_id' => $this->variantA->id, 'quantity' => 10, 'unit_cost' => 5],
            ['product_variant_id' => $this->variantA->id, 'quantity' => 5, 'unit_cost' => 5],
        ],
    ];

    $this->postJson('/api/admin/opening-stock-entry', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items.1.product_variant_id']);
});

it('accepts two different variants in the same opening stock entry', function () {
    $payload = [
        'date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'status' => StatusEnum::DRAFT->value,
        'items' => [
            ['product_variant_id' => $this->variantA->id, 'quantity' => 10, 'unit_cost' => 5],
            ['product_variant_id' => $this->variantB->id, 'quantity' => 5, 'unit_cost' => 5],
        ],
    ];

    $this->postJson('/api/admin/opening-stock-entry', $payload)
        ->assertCreated();
});

// ─── SCH-005: SerialNumber soft deletes ──────────────────────────────────────

it('serial numbers are soft-deleted not hard-deleted', function () {
    $serial = SerialNumber::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variantA->id,
        'warehouse_id' => $this->warehouse->id,
        'serial_no' => 'SN-TEST-001',
        'status' => 'in_stock',
    ]);

    $serial->delete();

    // Row must still exist with a deleted_at timestamp
    $this->assertSoftDeleted('serial_numbers', ['id' => $serial->id]);

    // Normal query should not return it
    expect(SerialNumber::find($serial->id))->toBeNull();
});

// ─── BUG-004 / BUG-005: company_id backfill and tenant scoping ───────────────

it('opening_stock_entry_items receives company_id via MultiTenant on create', function () {
    $entry = OpeningStockEntry::create([
        'company_id' => $this->company->id,
        'warehouse_id' => $this->warehouse->id,
        'date' => now()->toDateString(),
        'status' => StatusEnum::DRAFT,
        'create_user_id' => $this->user->id,
    ]);

    $item = $entry->openingStockEntryItems()->create([
        'product_variant_id' => $this->variantA->id,
        'quantity' => 5,
        'unit_cost' => 10,
    ]);

    expect($item->company_id)->toBe($this->company->id);
});

it('stock_transfer_items receives company_id via MultiTenant on create', function () {
    $transfer = StockTransfer::create([
        'company_id' => $this->company->id,
        'from_warehouse_id' => $this->warehouse->id,
        'to_warehouse_id' => $this->warehouseB->id,
        'date' => now()->toDateString(),
        'status' => StatusEnum::DRAFT,
        'create_user_id' => $this->user->id,
    ]);

    $item = $transfer->stockTransferItems()->create([
        'product_variant_id' => $this->variantA->id,
        'quantity' => 5,
    ]);

    expect($item->company_id)->toBe($this->company->id);
});

// ─── BRANCH-001: stock_transfers now has branch_id column ────────────────────

it('stock_transfers table has branch_id column', function () {
    expect(Schema::hasColumn('stock_transfers', 'branch_id'))->toBeTrue();
});

it('stock_transfers store branch_id from the tenant context', function () {
    $transfer = StockTransfer::create([
        'company_id' => $this->company->id,
        'from_warehouse_id' => $this->warehouse->id,
        'to_warehouse_id' => $this->warehouseB->id,
        'date' => now()->toDateString(),
        'status' => StatusEnum::DRAFT,
        'create_user_id' => $this->user->id,
    ]);

    expect($transfer->branch_id)->toBe($this->branch->id);
});

// ─── PERF-001: product/variant/all endpoint is bounded ───────────────────────

it('product variant all endpoint returns bounded results with a limit param', function () {
    $response = $this->getJson('/api/admin/product/variant/all?limit=1')
        ->assertOk();

    expect(count($response->json('data')))->toBeLessThanOrEqual(1);
});

it('product variant all endpoint supports search filtering', function () {
    // Create a second product with a different SKU
    $otherProduct = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Other Product',
        'code' => 'OTHER-P2',
        'product_type' => ProductTypeEnum::PRODUCT,
    ]);
    ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $otherProduct->id,
        'sku' => 'UNIQUE-SKU-XYZ',
        'is_default' => true,
    ]);

    $response = $this->getJson('/api/admin/product/variant/all?search=UNIQUE-SKU-XYZ')
        ->assertOk();

    $skus = collect($response->json('data'))->pluck('sku')->all();
    expect($skus)->toContain('UNIQUE-SKU-XYZ');
    expect(count($skus))->toBe(1);
});
