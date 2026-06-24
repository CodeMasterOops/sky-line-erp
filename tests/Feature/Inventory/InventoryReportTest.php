<?php

use App\Models\User;
use App\Models\Batch;
use App\Models\Stock;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Product;
use App\Enums\StatusEnum;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Enums\ChangeTypeEnum;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\ProductVariant;
use App\Services\TenantService;
use App\Enums\StockDirectionEnum;
use App\Models\OpeningStockEntry;
use App\Models\StockTransferItem;
use Illuminate\Support\Facades\DB;
use App\Models\OpeningStockEntryItem;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Report Test Co',
        'code' => 'RTC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Reporter',
        'email' => 'reporter-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Main Warehouse',
        'code' => 'MW',
    ]);

    $this->warehouseB = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Branch Warehouse',
        'code' => 'BW',
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Widget',
        'code' => 'WIDGET',
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'SKU-001',
        'is_default' => true,
    ]);

    Stock::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 100,
    ]);

    $movement = StockMovement::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => ChangeTypeEnum::OPENING_STOCK,
        'direction' => StockDirectionEnum::IN,
        'quantity' => 100,
        'unit_cost' => 10.00,
        'total_cost' => 1000.00,
    ]);

    $this->movementId = $movement->id;

    Sanctum::actingAs($this->user, ['*'], 'admin');
});

// Stock Movement Report
it('returns stock movement report with rows', function () {
    $response = $this->getJson('/api/admin/inventory-report/stock-movement');

    $response->assertOk()
        ->assertJsonPath('data.rows.0.product_name', 'Widget')
        ->assertJsonPath('data.rows.0.direction', 'in');

    expect($response->json('data.summary.total_in'))->toEqual(100);
});

it('filters stock movement report by direction out', function () {
    $response = $this->getJson('/api/admin/inventory-report/stock-movement?direction=out');

    $response->assertOk();
    expect(count($response->json('data.rows')))->toBe(0);
    expect($response->json('data.summary.total_out'))->toEqual(0);
});

it('filters stock movement report by product_variant_id', function () {
    $other = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'SKU-002',
    ]);

    $response = $this->getJson("/api/admin/inventory-report/stock-movement?product_variant_id={$other->id}");

    $response->assertOk();
    expect(count($response->json('data.rows')))->toBe(0);
});

// Stock Ledger Report
it('returns stock ledger with running balance', function () {
    $response = $this->getJson("/api/admin/inventory-report/stock-ledger?product_variant_id={$this->variant->id}");

    $response->assertOk();
    expect($response->json('data.opening_balance'))->toEqual(0);

    $rows = $response->json('data.rows');
    expect(count($rows))->toBeGreaterThan(0);
    expect($rows[0]['balance'])->toEqual(100);
});

it('returns empty ledger rows when no product selected', function () {
    $response = $this->getJson('/api/admin/inventory-report/stock-ledger');

    $response->assertOk();
    expect($response->json('data.opening_balance'))->toEqual(0);
    expect(count($response->json('data.rows')))->toBe(0);
});

// Warehouse Stock Report
it('returns warehouse stock grouped by warehouse', function () {
    $response = $this->getJson('/api/admin/inventory-report/warehouse-stock');

    $response->assertOk();
    $rows = $response->json('data.rows');
    expect(count($rows))->toBeGreaterThan(0);
    expect($rows[0]['warehouse'])->toBe('Main Warehouse');
    expect($rows[0]['total_quantity'])->toEqual(100);
});

it('filters warehouse stock by warehouse_id shows empty for unused warehouse', function () {
    $response = $this->getJson("/api/admin/inventory-report/warehouse-stock?warehouse_id={$this->warehouseB->id}");

    $response->assertOk();
    expect(count($response->json('data.rows')))->toBe(0);
});

// Warehouse Transfer Report
it('returns warehouse transfer report with items', function () {
    $transfer = StockTransfer::create([
        'company_id' => $this->company->id,
        'reference_no' => 'TRF-001',
        'date' => now()->toDateString(),
        'from_warehouse_id' => $this->warehouse->id,
        'to_warehouse_id' => $this->warehouseB->id,
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
    ]);

    StockTransferItem::create([
        'stock_transfer_id' => $transfer->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 20,
    ]);

    $response = $this->getJson('/api/admin/inventory-report/warehouse-transfer');

    $response->assertOk();
    $rows = $response->json('data.rows');
    expect(count($rows))->toBe(1);
    expect($rows[0]['reference_no'])->toBe('TRF-001');
    expect($rows[0]['total_quantity'])->toEqual(20);
    expect($rows[0]['items'][0]['product_name'])->toBe('Widget');
});

it('returns empty transfers when no matching date range', function () {
    $response = $this->getJson('/api/admin/inventory-report/warehouse-transfer?from_date=2020-01-01&to_date=2020-01-31');

    $response->assertOk();
    expect(count($response->json('data.rows')))->toBe(0);
});

// Expiry Stock Report
it('returns near expiry batches within specified days', function () {
    Batch::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'batch_no' => 'BATCH-001',
        'expiry_date' => now()->addDays(15)->toDateString(),
        'remaining_qty' => 50,
        'unit_cost' => 10.00,
        'status' => 'active',
    ]);

    $response = $this->getJson('/api/admin/inventory-report/expiry-stock?type=near_expiry&days=30');

    $response->assertOk();
    expect(count($response->json('data.rows')))->toBe(1);
    expect($response->json('data.rows.0.batch_no'))->toBe('BATCH-001');
});

it('returns expired batches', function () {
    Batch::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'batch_no' => 'BATCH-EXP',
        'expiry_date' => now()->subDays(5)->toDateString(),
        'remaining_qty' => 10,
        'unit_cost' => 5.00,
        'status' => 'active',
    ]);

    $response = $this->getJson('/api/admin/inventory-report/expiry-stock?type=expired');

    $response->assertOk();
    expect(count($response->json('data.rows')))->toBe(1);
    expect($response->json('data.rows.0.batch_no'))->toBe('BATCH-EXP');
});

it('excludes batches expiring beyond the days threshold', function () {
    Batch::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'batch_no' => 'BATCH-FAR',
        'expiry_date' => now()->addDays(90)->toDateString(),
        'remaining_qty' => 20,
        'unit_cost' => 8.00,
        'status' => 'active',
    ]);

    $response = $this->getJson('/api/admin/inventory-report/expiry-stock?type=near_expiry&days=30');

    $response->assertOk();
    expect(count($response->json('data.rows')))->toBe(0);
});

// Dead Stock Report
it('returns products with no movement after cutoff as dead stock', function () {
    $staleVariant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'STALE-001',
    ]);

    Stock::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $staleVariant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 30,
    ]);

    $staleMovement = StockMovement::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $staleVariant->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => ChangeTypeEnum::OPENING_STOCK,
        'direction' => StockDirectionEnum::IN,
        'quantity' => 30,
        'unit_cost' => 5.00,
        'total_cost' => 150.00,
    ]);

    // Push the movement's created_at back past the cutoff
    DB::table('stock_movements')
        ->where('id', $staleMovement->id)
        ->update(['created_at' => now()->subDays(120)]);

    $response = $this->getJson('/api/admin/inventory-report/dead-stock?days=90');

    $response->assertOk();
    $rows = $response->json('data.rows');
    $staleRow = collect($rows)->firstWhere('sku', 'STALE-001');
    expect($staleRow)->not->toBeNull();
});

it('excludes products with recent movement from dead stock', function () {
    $response = $this->getJson('/api/admin/inventory-report/dead-stock?days=90');

    $response->assertOk();
    $rows = collect($response->json('data.rows'));
    // SKU-001 has a movement created just now, so it should NOT appear as dead stock
    $activeRow = $rows->firstWhere('sku', 'SKU-001');
    expect($activeRow)->toBeNull();
});

// Stock Opening Report
it('returns opening stock entries with items', function () {
    $entry = OpeningStockEntry::create([
        'company_id' => $this->company->id,
        'reference_no' => 'OSE-001',
        'date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
    ]);

    OpeningStockEntryItem::create([
        'opening_stock_entry_id' => $entry->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 100,
        'unit_cost' => 10.00,
    ]);

    $response = $this->getJson('/api/admin/inventory-report/stock-opening');

    $response->assertOk();
    $rows = $response->json('data.rows');
    expect(count($rows))->toBeGreaterThan(0);
    $found = collect($rows)->firstWhere('reference_no', 'OSE-001');
    expect($found)->not->toBeNull();
    expect($found['total_quantity'])->toEqual(100);
    expect($found['total_value'])->toEqual(1000);
});

it('filters stock opening by warehouse_id', function () {
    OpeningStockEntry::create([
        'company_id' => $this->company->id,
        'reference_no' => 'OSE-002',
        'date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
    ]);

    $response = $this->getJson("/api/admin/inventory-report/stock-opening?warehouse_id={$this->warehouseB->id}");

    $response->assertOk();
    expect(count($response->json('data.rows')))->toBe(0);
});

// Branch Isolation Tests
it('warehouse transfer report scopes results to the active branch', function () {
    $branch = Branch::create([
        'company_id' => $this->company->id,
        'name' => 'Branch A',
        'code' => 'BA',
    ]);

    $otherBranch = Branch::create([
        'company_id' => $this->company->id,
        'name' => 'Branch B',
        'code' => 'BB',
    ]);

    // Transfer belonging to $branch
    $branchTransfer = StockTransfer::create([
        'company_id' => $this->company->id,
        'branch_id' => $branch->id,
        'reference_no' => 'TRF-BRANCH-A',
        'date' => now()->toDateString(),
        'from_warehouse_id' => $this->warehouse->id,
        'to_warehouse_id' => $this->warehouseB->id,
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
    ]);

    StockTransferItem::create([
        'stock_transfer_id' => $branchTransfer->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 10,
    ]);

    // Transfer belonging to $otherBranch
    $otherTransfer = StockTransfer::create([
        'company_id' => $this->company->id,
        'branch_id' => $otherBranch->id,
        'reference_no' => 'TRF-BRANCH-B',
        'date' => now()->toDateString(),
        'from_warehouse_id' => $this->warehouseB->id,
        'to_warehouse_id' => $this->warehouse->id,
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
    ]);

    StockTransferItem::create([
        'stock_transfer_id' => $otherTransfer->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 5,
    ]);

    TenantService::setCompanyId($this->company->id);
    TenantService::setBranchId($branch->id);

    $response = $this->getJson('/api/admin/inventory-report/warehouse-transfer');

    $response->assertOk();
    $refs = collect($response->json('data.rows'))->pluck('reference_no');
    expect($refs->contains('TRF-BRANCH-A'))->toBeTrue();
    expect($refs->contains('TRF-BRANCH-B'))->toBeFalse();
});

it('stock movement report scopes results to the active branch', function () {
    $branchA = Branch::create(['company_id' => $this->company->id, 'name' => 'SM-A', 'code' => 'SMA']);
    $branchB = Branch::create(['company_id' => $this->company->id, 'name' => 'SM-B', 'code' => 'SMB']);

    foreach ([[$branchA, 'SM-BRANCH-A'], [$branchB, 'SM-BRANCH-B']] as [$branch, $remark]) {
        StockMovement::create([
            'company_id' => $this->company->id,
            'branch_id' => $branch->id,
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => ChangeTypeEnum::PURCHASE,
            'direction' => StockDirectionEnum::IN,
            'quantity' => 10,
            'unit_cost' => 10.00,
            'total_cost' => 100.00,
            'remarks' => $remark,
            'created_at' => now(),
        ]);
    }

    TenantService::setCompanyId($this->company->id);
    TenantService::setBranchId($branchA->id);

    $response = $this->getJson('/api/admin/inventory-report/stock-movement');

    $response->assertOk();
    $remarks = collect($response->json('data.rows'))->pluck('remarks');
    expect($remarks->contains('SM-BRANCH-A'))->toBeTrue();
    expect($remarks->contains('SM-BRANCH-B'))->toBeFalse();
});

// Inventory Summary Report
it('returns inventory summary with opening, purchase, sale and closing columns', function () {
    // Purchase movement in the fiscal year period
    StockMovement::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => ChangeTypeEnum::PURCHASE,
        'direction' => StockDirectionEnum::IN,
        'quantity' => 50,
        'unit_cost' => 10.00,
        'total_cost' => 500.00,
        'created_at' => '2026-03-01 10:00:00',
    ]);

    // Sale movement in the fiscal year period
    StockMovement::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => ChangeTypeEnum::SALE,
        'direction' => StockDirectionEnum::OUT,
        'quantity' => 20,
        'unit_cost' => 10.00,
        'total_cost' => 200.00,
        'created_at' => '2026-04-01 10:00:00',
    ]);

    // Sale return in the fiscal year period
    StockMovement::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => ChangeTypeEnum::RETURN_IN,
        'direction' => StockDirectionEnum::IN,
        'quantity' => 5,
        'unit_cost' => 10.00,
        'total_cost' => 50.00,
        'created_at' => '2026-04-05 10:00:00',
    ]);

    // Purchase return in the fiscal year period
    StockMovement::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => ChangeTypeEnum::RETURN_OUT,
        'direction' => StockDirectionEnum::OUT,
        'quantity' => 10,
        'unit_cost' => 10.00,
        'total_cost' => 100.00,
        'created_at' => '2026-04-10 10:00:00',
    ]);

    $response = $this->getJson('/api/admin/inventory-report/inventory-summary?from_date=2026-01-01&to_date=2026-12-31');

    $response->assertOk();
    $rows = $response->json('data.rows');
    expect($rows)->not->toBeEmpty();

    $row = collect($rows)->firstWhere('sku', 'SKU-001');
    expect($row)->not->toBeNull();
    expect($row['purchase_qty'])->toEqual(50.0);
    expect($row['purchase_value'])->toEqual(500.0);
    expect($row['purchase_return_qty'])->toEqual(10.0);
    expect($row['sale_qty'])->toEqual(20.0);
    expect($row['sale_return_qty'])->toEqual(5.0);
    // closing_qty = opening(100) + purchase(50) - purchase_return(10) - sale(20) + sale_return(5) = 125
    expect($row['closing_qty'])->toEqual(125.0);
});

it('returns zero in-period columns when date range has no activity', function () {
    $response = $this->getJson('/api/admin/inventory-report/inventory-summary?from_date=2030-01-01&to_date=2030-12-31');

    $response->assertOk();
    $row = collect($response->json('data.rows'))->firstWhere('sku', 'SKU-001');
    expect($row)->not->toBeNull();
    expect($row['purchase_qty'])->toEqual(0.0);
    expect($row['sale_qty'])->toEqual(0.0);
    expect($row['sale_return_qty'])->toEqual(0.0);
    expect($row['purchase_return_qty'])->toEqual(0.0);
});

it('filters inventory summary by category', function () {
    $otherProduct = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Other Product',
        'code' => 'OTHER',
    ]);

    $otherVariant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $otherProduct->id,
        'sku' => 'SKU-OTHER',
        'is_default' => true,
    ]);

    StockMovement::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $otherVariant->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => ChangeTypeEnum::OPENING_STOCK,
        'direction' => StockDirectionEnum::IN,
        'quantity' => 10,
        'unit_cost' => 5.00,
        'total_cost' => 50.00,
    ]);

    // Filter by a non-existent category to get empty results
    $response = $this->getJson('/api/admin/inventory-report/inventory-summary?category_id=99999');

    $response->assertOk();
    expect($response->json('data.rows'))->toBeEmpty();
});

it('inventory summary returns filter_options on first load', function () {
    $response = $this->getJson('/api/admin/inventory-report/inventory-summary?with_options=1');

    $response->assertOk();
    expect($response->json('data.filter_options'))->not->toBeNull();
    expect($response->json('data.filter_options.warehouse_options'))->toBeArray();
    expect($response->json('data.filter_options.category_options'))->toBeArray();
});

it('stock opening report scopes results to the active branch', function () {
    $branch = Branch::create([
        'company_id' => $this->company->id,
        'name' => 'Branch A',
        'code' => 'BA2',
    ]);

    $otherBranch = Branch::create([
        'company_id' => $this->company->id,
        'name' => 'Branch B',
        'code' => 'BB2',
    ]);

    // Opening entry for $branch
    $branchEntry = OpeningStockEntry::create([
        'company_id' => $this->company->id,
        'branch_id' => $branch->id,
        'reference_no' => 'OSE-BRANCH-A',
        'date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
    ]);

    OpeningStockEntryItem::create([
        'opening_stock_entry_id' => $branchEntry->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 50,
        'unit_cost' => 10.00,
    ]);

    // Opening entry for $otherBranch
    $otherEntry = OpeningStockEntry::create([
        'company_id' => $this->company->id,
        'branch_id' => $otherBranch->id,
        'reference_no' => 'OSE-BRANCH-B',
        'date' => now()->toDateString(),
        'warehouse_id' => $this->warehouseB->id,
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
    ]);

    OpeningStockEntryItem::create([
        'opening_stock_entry_id' => $otherEntry->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 25,
        'unit_cost' => 10.00,
    ]);

    TenantService::setCompanyId($this->company->id);
    TenantService::setBranchId($branch->id);

    $response = $this->getJson('/api/admin/inventory-report/stock-opening');

    $response->assertOk();
    $refs = collect($response->json('data.rows'))->pluck('reference_no');
    expect($refs->contains('OSE-BRANCH-A'))->toBeTrue();
    expect($refs->contains('OSE-BRANCH-B'))->toBeFalse();
});
