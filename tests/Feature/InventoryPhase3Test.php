<?php

use App\Models\Bom;
use App\Models\User;
use App\Models\Batch;
use App\Models\Party;
use App\Models\Stock;
use App\Models\Account;
use App\Models\BomItem;
use App\Models\Company;
use App\Models\Product;
use App\Enums\StatusEnum;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Models\StockLayer;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Enums\ChangeTypeEnum;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use App\Services\TenantService;
use App\Models\StockReservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use App\Services\Inventory\InventoryLayerReceiptService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function invP3WarmCache(): void
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
    invP3WarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026-P3', 'year_code' => '26P3',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Phase3 Test Co',
        'code' => 'P3TC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Phase3 Tester',
        'email' => 'p3-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->warehouseA = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'P3 Source', 'code' => 'P3SRC',
    ]);

    $this->warehouseB = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'P3 Dest', 'code' => 'P3DST',
    ]);

    $rawProduct = Product::create(['company_id' => $this->company->id, 'name' => 'P3 Raw', 'code' => 'P3RAW']);
    $this->rawVariant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $rawProduct->id,
        'sku' => 'P3RAW-1', 'is_default' => true,
        'purchase_price' => 20.0,
    ]);

    $fgProduct = Product::create(['company_id' => $this->company->id, 'name' => 'P3 FG', 'code' => 'P3FG']);
    $this->fgVariant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $fgProduct->id,
        'sku' => 'P3FG-1', 'is_default' => true,
    ]);

    $this->inventoryAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'P3 Inventory', 'code' => 'P3-INV',
    ]);
    $this->cogsAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'P3 COGS', 'code' => 'P3-COGS',
    ]);
    $this->wipAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'P3 WIP', 'code' => 'P3-WIP',
    ]);
    $this->stockAdjAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'P3 Stock Adj', 'code' => 'P3-SADJ',
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'inventory_account_id' => $this->inventoryAccount->id,
        'cogs_account_id' => $this->cogsAccount->id,
        'wip_account_id' => $this->wipAccount->id,
        'stock_adjustment_account_id' => $this->stockAdjAccount->id,
    ]);

    // BOM: 3 raw → 1 FG
    $this->bom = Bom::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->fgVariant->id,
        'name' => 'P3 BOM v1', 'version' => '1',
        'output_qty' => 1, 'is_active' => true, 'is_default' => true,
    ]);
    BomItem::create([
        'bom_id' => $this->bom->id,
        'product_variant_id' => $this->rawVariant->id,
        'quantity' => 3,
        'item_type' => 'material',
        'wastage_pct' => 0,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

function invP3SeedStock(object $test, int $qty, float $unitCost, ?Warehouse $warehouse = null): void
{
    $wh = $warehouse ?? $test->warehouseA;
    $adj = StockAdjustment::create([
        'company_id' => $test->company->id,
        'reference_no' => 'P3SEED-'.uniqid(),
        'date' => now()->toDateString(),
        'warehouse_id' => $wh->id,
        'create_user_id' => $test->user->id,
        'status' => 'approved',
    ]);

    $receipt = app(InventoryLayerReceiptService::class);
    DB::transaction(fn () => $receipt->receive(
        $test->company, $adj, $test->rawVariant->id,
        $wh->id, $qty, $unitCost,
        ChangeTypeEnum::PURCHASE, $test->user->id, null,
    ));
}

// ============================================================
// Stock Reservation Tests
// ============================================================

it('reserves stock on production order creation and increments on_hold', function () {
    invP3SeedStock($this, 10, 20.0);

    $this->postJson('/api/admin/production-order', [
        'bom_id' => $this->bom->id,
        'warehouse_id' => $this->warehouseA->id,
        'planned_qty' => 2,
        'planned_start_date' => now()->toDateString(),
        'planned_end_date' => now()->addDay()->toDateString(),
    ])->assertSuccessful();

    // 2 produced × 3 raw each = 6 reserved
    $reservation = StockReservation::where('company_id', $this->company->id)
        ->where('product_variant_id', $this->rawVariant->id)
        ->where('warehouse_id', $this->warehouseA->id)
        ->whereNull('released_at')
        ->first();

    expect($reservation)->not->toBeNull();
    expect((int) $reservation->quantity)->toBe(6);

    $stock = Stock::where('company_id', $this->company->id)
        ->where('product_variant_id', $this->rawVariant->id)
        ->where('warehouse_id', $this->warehouseA->id)
        ->first();

    expect((int) $stock->on_hold)->toBe(6);
});

it('releases reservations and decrements on_hold when production order is cancelled', function () {
    invP3SeedStock($this, 10, 20.0);

    $createResponse = $this->postJson('/api/admin/production-order', [
        'bom_id' => $this->bom->id,
        'warehouse_id' => $this->warehouseA->id,
        'planned_qty' => 2,
        'planned_start_date' => now()->toDateString(),
        'planned_end_date' => now()->addDay()->toDateString(),
    ])->assertSuccessful();

    $orderId = $createResponse->json('data.id');

    $this->postJson("/api/admin/production-order/{$orderId}/cancel")
        ->assertSuccessful();

    $openReservations = StockReservation::where('company_id', $this->company->id)
        ->where('product_variant_id', $this->rawVariant->id)
        ->whereNull('released_at')
        ->count();

    expect($openReservations)->toBe(0);

    $stock = Stock::where('company_id', $this->company->id)
        ->where('product_variant_id', $this->rawVariant->id)
        ->where('warehouse_id', $this->warehouseA->id)
        ->first();

    expect((int) $stock->on_hold)->toBe(0);
});

it('fails reservation when insufficient available stock (quantity - on_hold)', function () {
    // Seed 4 units, BOM needs 3 per FG, so after first order (3 on hold) only 1 free
    invP3SeedStock($this, 4, 20.0);

    $this->postJson('/api/admin/production-order', [
        'bom_id' => $this->bom->id,
        'warehouse_id' => $this->warehouseA->id,
        'planned_qty' => 1, // uses 3 of 4 available
        'planned_start_date' => now()->toDateString(),
        'planned_end_date' => now()->addDay()->toDateString(),
    ])->assertSuccessful();

    // Second order needs 3 more but only 1 available (4 - 3 on hold)
    $this->postJson('/api/admin/production-order', [
        'bom_id' => $this->bom->id,
        'warehouse_id' => $this->warehouseA->id,
        'planned_qty' => 1,
        'planned_start_date' => now()->toDateString(),
        'planned_end_date' => now()->addDay()->toDateString(),
    ])->assertUnprocessable();
});

it('creates one StockReservation per distinct BOM material', function () {
    $otherProduct = Product::create(['company_id' => $this->company->id, 'name' => 'P3 Other', 'code' => 'P3OTH']);
    $otherVariant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $otherProduct->id,
        'sku' => 'P3OTH-1', 'is_default' => true,
    ]);
    BomItem::create([
        'bom_id' => $this->bom->id,
        'product_variant_id' => $otherVariant->id,
        'quantity' => 1,
        'item_type' => 'material',
        'wastage_pct' => 0,
    ]);

    invP3SeedStock($this, 5, 20.0);

    $adj = StockAdjustment::create([
        'company_id' => $this->company->id,
        'reference_no' => 'P3OTH-SEED',
        'date' => now()->toDateString(),
        'warehouse_id' => $this->warehouseA->id,
        'create_user_id' => $this->user->id,
        'status' => 'approved',
    ]);
    DB::transaction(fn () => app(InventoryLayerReceiptService::class)->receive(
        $this->company, $adj, $otherVariant->id,
        $this->warehouseA->id, 5, 10.0,
        ChangeTypeEnum::PURCHASE, $this->user->id, null,
    ));

    $this->postJson('/api/admin/production-order', [
        'bom_id' => $this->bom->id,
        'warehouse_id' => $this->warehouseA->id,
        'planned_qty' => 1,
        'planned_start_date' => now()->toDateString(),
        'planned_end_date' => now()->addDay()->toDateString(),
    ])->assertSuccessful();

    $reservations = StockReservation::where('company_id', $this->company->id)
        ->whereNull('released_at')
        ->count();

    expect($reservations)->toBe(2);
});

// ============================================================
// Batch Traceability Tests
// ============================================================

it('creates a Batch record when GRN item has a batch_no', function () {
    $supplier = Party::create([
        'company_id' => $this->company->id,
        'type' => 'supplier',
        'name' => 'P3 Supplier',
        'code' => 'P3SUP',
    ]);

    $createResponse = $this->postJson('/api/admin/grn', [
        'party_id' => $supplier->id,
        'warehouse_id' => $this->warehouseA->id,
        'received_date' => now()->toDateString(),
        'items' => [[
            'product_variant_id' => $this->rawVariant->id,
            'received_qty' => 10,
            'unit_cost' => 20.0,
            'batch_no' => 'BATCH-001',
            'expiry_date' => '2027-12-31',
        ]],
    ])->assertSuccessful();

    $grnId = $createResponse->json('data.id');

    $this->postJson("/api/admin/grn/{$grnId}/approve")
        ->assertSuccessful();

    $batch = Batch::where('company_id', $this->company->id)
        ->where('product_variant_id', $this->rawVariant->id)
        ->where('batch_no', 'BATCH-001')
        ->first();

    expect($batch)->not->toBeNull();
    expect((int) $batch->initial_qty)->toBe(10);
    expect((int) $batch->remaining_qty)->toBe(10);
    expect($batch->expiry_date->format('Y-m-d'))->toBe('2027-12-31');
});

it('links stock layer to the batch record after GRN approval', function () {
    $supplier = Party::create([
        'company_id' => $this->company->id,
        'type' => 'supplier',
        'name' => 'P3 Supplier2',
        'code' => 'P3SUP2',
    ]);

    $createResponse = $this->postJson('/api/admin/grn', [
        'party_id' => $supplier->id,
        'warehouse_id' => $this->warehouseA->id,
        'received_date' => now()->toDateString(),
        'items' => [[
            'product_variant_id' => $this->rawVariant->id,
            'received_qty' => 5,
            'unit_cost' => 20.0,
            'batch_no' => 'BATCH-LNK-001',
        ]],
    ])->assertSuccessful();

    $grnId = $createResponse->json('data.id');
    $this->postJson("/api/admin/grn/{$grnId}/approve")->assertSuccessful();

    $batch = Batch::where('batch_no', 'BATCH-LNK-001')
        ->where('company_id', $this->company->id)
        ->first();
    expect($batch)->not->toBeNull();

    $layer = StockLayer::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('product_variant_id', $this->rawVariant->id)
        ->where('batch_id', $batch->id)
        ->first();

    expect($layer)->not->toBeNull();
    expect((int) $layer->qty_remaining)->toBe(5);
});

it('increments existing batch qty when same batch_no received again', function () {
    $supplier = Party::create([
        'company_id' => $this->company->id,
        'type' => 'supplier',
        'name' => 'P3 Supplier3',
        'code' => 'P3SUP3',
    ]);

    $grnPayload = fn () => [
        'party_id' => $supplier->id,
        'warehouse_id' => $this->warehouseA->id,
        'received_date' => now()->toDateString(),
        'items' => [[
            'product_variant_id' => $this->rawVariant->id,
            'received_qty' => 5,
            'unit_cost' => 20.0,
            'batch_no' => 'BATCH-DUP-001',
        ]],
    ];

    $r1 = $this->postJson('/api/admin/grn', $grnPayload())->assertSuccessful();
    $this->postJson("/api/admin/grn/{$r1->json('data.id')}/approve")->assertSuccessful();

    $r2 = $this->postJson('/api/admin/grn', $grnPayload())->assertSuccessful();
    $this->postJson("/api/admin/grn/{$r2->json('data.id')}/approve")->assertSuccessful();

    $batchCount = Batch::where('company_id', $this->company->id)
        ->where('batch_no', 'BATCH-DUP-001')
        ->count();
    expect($batchCount)->toBe(1);

    $batch = Batch::where('batch_no', 'BATCH-DUP-001')
        ->where('company_id', $this->company->id)
        ->first();
    expect((int) $batch->remaining_qty)->toBe(10);
    expect((int) $batch->initial_qty)->toBe(10);
});

it('does not create a batch record when GRN item has no batch_no', function () {
    $supplier = Party::create([
        'company_id' => $this->company->id,
        'type' => 'supplier',
        'name' => 'P3 Supplier4',
        'code' => 'P3SUP4',
    ]);

    $r = $this->postJson('/api/admin/grn', [
        'party_id' => $supplier->id,
        'warehouse_id' => $this->warehouseA->id,
        'received_date' => now()->toDateString(),
        'items' => [[
            'product_variant_id' => $this->rawVariant->id,
            'received_qty' => 5,
            'unit_cost' => 20.0,
        ]],
    ])->assertSuccessful();

    $this->postJson("/api/admin/grn/{$r->json('data.id')}/approve")->assertSuccessful();

    $batchCount = Batch::where('company_id', $this->company->id)
        ->where('product_variant_id', $this->rawVariant->id)
        ->count();

    expect($batchCount)->toBe(0);

    $layerWithBatch = StockLayer::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('product_variant_id', $this->rawVariant->id)
        ->whereNotNull('batch_id')
        ->first();

    expect($layerWithBatch)->toBeNull();
});

// ============================================================
// In-Transit Stock Transfer Tests
// ============================================================

it('dispatching a transfer sets status to in_transit and creates TRANSFER_OUT movement', function () {
    invP3SeedStock($this, 10, 20.0);

    $r = $this->postJson('/api/admin/stock-transfer', [
        'date' => now()->toDateString(),
        'from_warehouse_id' => $this->warehouseA->id,
        'to_warehouse_id' => $this->warehouseB->id,
        'items' => [[
            'product_variant_id' => $this->rawVariant->id,
            'quantity' => 4,
            'from_warehouse_id' => $this->warehouseA->id,
        ]],
    ])->assertSuccessful();

    $transferId = $r->json('data.id');

    $this->postJson("/api/admin/stock-transfer/{$transferId}/dispatch")
        ->assertSuccessful();

    $transfer = StockTransfer::find($transferId);
    expect($transfer->status)->toBe(StatusEnum::IN_TRANSIT);
    expect($transfer->dispatched_at)->not->toBeNull();
    expect($transfer->dispatch_user_id)->toBe($this->user->id);

    // Source stock deducted immediately
    $srcStock = Stock::where('company_id', $this->company->id)
        ->where('product_variant_id', $this->rawVariant->id)
        ->where('warehouse_id', $this->warehouseA->id)
        ->first();
    expect((int) $srcStock->quantity)->toBe(6);

    // Destination not touched yet
    $dstQty = Stock::where('company_id', $this->company->id)
        ->where('product_variant_id', $this->rawVariant->id)
        ->where('warehouse_id', $this->warehouseB->id)
        ->value('quantity') ?? 0;
    expect((int) $dstQty)->toBe(0);

    // TRANSFER_OUT movement exists
    $outMovement = StockMovement::where('reference_id', $transferId)
        ->where('reference_type', StockTransfer::class)
        ->where('type', ChangeTypeEnum::TRANSFER_OUT)
        ->first();
    expect($outMovement)->not->toBeNull();
    expect((int) $outMovement->quantity)->toBe(4);
});

it('receiving an in-transit transfer sets status to approved and creates TRANSFER_IN movement', function () {
    invP3SeedStock($this, 10, 20.0);

    $r = $this->postJson('/api/admin/stock-transfer', [
        'date' => now()->toDateString(),
        'from_warehouse_id' => $this->warehouseA->id,
        'to_warehouse_id' => $this->warehouseB->id,
        'items' => [[
            'product_variant_id' => $this->rawVariant->id,
            'quantity' => 4,
            'from_warehouse_id' => $this->warehouseA->id,
        ]],
    ])->assertSuccessful();

    $transferId = $r->json('data.id');

    $this->postJson("/api/admin/stock-transfer/{$transferId}/dispatch")->assertSuccessful();
    $this->postJson("/api/admin/stock-transfer/{$transferId}/receive")->assertSuccessful();

    $transfer = StockTransfer::find($transferId);
    expect($transfer->status)->toBe(StatusEnum::APPROVED);
    expect($transfer->received_at)->not->toBeNull();
    expect($transfer->approved_at)->not->toBeNull();

    // Destination stock added
    $dstStock = Stock::where('company_id', $this->company->id)
        ->where('product_variant_id', $this->rawVariant->id)
        ->where('warehouse_id', $this->warehouseB->id)
        ->first();
    expect((int) $dstStock->quantity)->toBe(4);

    // TRANSFER_IN movement exists with matching cost
    $outMovement = StockMovement::where('reference_id', $transferId)
        ->where('reference_type', StockTransfer::class)
        ->where('type', ChangeTypeEnum::TRANSFER_OUT)
        ->first();

    $inMovement = StockMovement::where('reference_id', $transferId)
        ->where('reference_type', StockTransfer::class)
        ->where('type', ChangeTypeEnum::TRANSFER_IN)
        ->first();

    expect($inMovement)->not->toBeNull();
    expect((int) $inMovement->quantity)->toBe(4);
    expect((float) $inMovement->unit_cost)->toBe((float) $outMovement->unit_cost);
});

it('dispatch fails if transfer is already in_transit', function () {
    invP3SeedStock($this, 10, 20.0);

    $r = $this->postJson('/api/admin/stock-transfer', [
        'date' => now()->toDateString(),
        'from_warehouse_id' => $this->warehouseA->id,
        'to_warehouse_id' => $this->warehouseB->id,
        'items' => [[
            'product_variant_id' => $this->rawVariant->id,
            'quantity' => 4,
            'from_warehouse_id' => $this->warehouseA->id,
        ]],
    ])->assertSuccessful();

    $transferId = $r->json('data.id');

    $this->postJson("/api/admin/stock-transfer/{$transferId}/dispatch")->assertSuccessful();
    $this->postJson("/api/admin/stock-transfer/{$transferId}/dispatch")->assertUnprocessable();
});

it('receive fails if transfer is still in draft status', function () {
    invP3SeedStock($this, 10, 20.0);

    $r = $this->postJson('/api/admin/stock-transfer', [
        'date' => now()->toDateString(),
        'from_warehouse_id' => $this->warehouseA->id,
        'to_warehouse_id' => $this->warehouseB->id,
        'items' => [[
            'product_variant_id' => $this->rawVariant->id,
            'quantity' => 4,
            'from_warehouse_id' => $this->warehouseA->id,
        ]],
    ])->assertSuccessful();

    $transferId = $r->json('data.id');

    $this->postJson("/api/admin/stock-transfer/{$transferId}/receive")->assertUnprocessable();
});

it('dispatch fails when source warehouse has insufficient stock', function () {
    invP3SeedStock($this, 2, 20.0);

    $r = $this->postJson('/api/admin/stock-transfer', [
        'date' => now()->toDateString(),
        'from_warehouse_id' => $this->warehouseA->id,
        'to_warehouse_id' => $this->warehouseB->id,
        'items' => [[
            'product_variant_id' => $this->rawVariant->id,
            'quantity' => 5,
            'from_warehouse_id' => $this->warehouseA->id,
        ]],
    ])->assertSuccessful();

    $transferId = $r->json('data.id');

    $this->postJson("/api/admin/stock-transfer/{$transferId}/dispatch")->assertUnprocessable();
});

it('in-transit transfer preserves FIFO cost at destination', function () {
    $receipt = app(InventoryLayerReceiptService::class);

    $adj1 = StockAdjustment::create([
        'company_id' => $this->company->id, 'reference_no' => 'P3FIFO-1',
        'date' => now()->toDateString(), 'warehouse_id' => $this->warehouseA->id,
        'create_user_id' => $this->user->id, 'status' => 'approved',
    ]);
    DB::transaction(fn () => $receipt->receive(
        $this->company, $adj1, $this->rawVariant->id,
        $this->warehouseA->id, 5, 10.0,
        ChangeTypeEnum::PURCHASE, $this->user->id, null,
    ));

    $adj2 = StockAdjustment::create([
        'company_id' => $this->company->id, 'reference_no' => 'P3FIFO-2',
        'date' => now()->toDateString(), 'warehouse_id' => $this->warehouseA->id,
        'create_user_id' => $this->user->id, 'status' => 'approved',
    ]);
    DB::transaction(fn () => $receipt->receive(
        $this->company, $adj2, $this->rawVariant->id,
        $this->warehouseA->id, 5, 30.0,
        ChangeTypeEnum::PURCHASE, $this->user->id, null,
    ));

    // Transfer exactly the first (cheaper) FIFO layer
    $r = $this->postJson('/api/admin/stock-transfer', [
        'date' => now()->toDateString(),
        'from_warehouse_id' => $this->warehouseA->id,
        'to_warehouse_id' => $this->warehouseB->id,
        'items' => [[
            'product_variant_id' => $this->rawVariant->id,
            'quantity' => 5,
            'from_warehouse_id' => $this->warehouseA->id,
        ]],
    ])->assertSuccessful();

    $transferId = $r->json('data.id');
    $this->postJson("/api/admin/stock-transfer/{$transferId}/dispatch")->assertSuccessful();
    $this->postJson("/api/admin/stock-transfer/{$transferId}/receive")->assertSuccessful();

    $dstLayer = StockLayer::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('product_variant_id', $this->rawVariant->id)
        ->where('warehouse_id', $this->warehouseB->id)
        ->orderBy('id', 'desc')
        ->first();

    expect($dstLayer)->not->toBeNull();
    expect((float) $dstLayer->unit_cost)->toBe(10.0);
});
