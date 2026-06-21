<?php

use App\Models\Batch;
use App\Models\Stock;
use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Models\StockLayer;
use App\Enums\BatchStatusEnum;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use App\Services\Inventory\StockLayerLedger;
use Illuminate\Validation\ValidationException;
use App\Services\Inventory\InventoryStockReconciliationAlignService;

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
        'company_name' => 'Auto Consume Co',
        'code' => 'ACC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Warehouse A',
        'code' => 'WA',
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Tracked Widget',
        'code' => 'TW',
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'SKU-AC',
        'is_default' => true,
        'is_batch_tracked' => true,
    ]);

    TenantService::setCompanyId($this->company->id);
});

/**
 * Create an active and a held lot, each backed by a cost layer, plus the on-hand row.
 * Returns [activeBatch, heldBatch].
 */
function seedConsumeScenario(object $test, BatchStatusEnum $heldStatus, int $activeQty = 60, int $heldQty = 40): array
{
    $make = function (BatchStatusEnum $status, int $qty, int $order) use ($test) {
        $batch = Batch::create([
            'company_id' => $test->company->id,
            'product_variant_id' => $test->variant->id,
            'warehouse_id' => $test->warehouse->id,
            'batch_no' => 'B-'.$order.'-'.uniqid(),
            'initial_qty' => $qty,
            'remaining_qty' => $qty,
            'unit_cost' => 10,
            'status' => $status,
        ]);

        StockLayer::create([
            'company_id' => $test->company->id,
            'product_variant_id' => $test->variant->id,
            'warehouse_id' => $test->warehouse->id,
            'qty_remaining' => $qty,
            'unit_cost' => 10,
            'received_at' => now()->addSeconds($order),
            'batch_id' => $batch->id,
        ]);

        return $batch;
    };

    // Held lot is received first, so a naive FIFO would consume it before the active lot.
    $held = $make($heldStatus, $heldQty, 1);
    $active = $make(BatchStatusEnum::Active, $activeQty, 2);

    Stock::withoutGlobalScopes()->create([
        'company_id' => $test->company->id,
        'product_variant_id' => $test->variant->id,
        'warehouse_id' => $test->warehouse->id,
        'quantity' => $activeQty + $heldQty,
        'on_hold' => 0,
    ]);

    return [$active, $held];
}

it('FIFO auto-consume skips held layers and never draws them down', function () {
    [$active, $recalled] = seedConsumeScenario($this, BatchStatusEnum::Recalled, activeQty: 60, heldQty: 40);

    $ledger = app(StockLayerLedger::class);
    $lines = $ledger->consume($this->company, $this->variant->id, $this->warehouse->id, 60, null);

    // All 60 came out of the active lot; the recalled lot is untouched.
    expect((int) collect($lines)->sum('quantity'))->toBe(60);
    expect((int) StockLayer::withoutGlobalScopes()->where('batch_id', $active->id)->sum('qty_remaining'))->toBe(0);
    expect((int) StockLayer::withoutGlobalScopes()->where('batch_id', $recalled->id)->sum('qty_remaining'))->toBe(40);
});

it('FIFO auto-consume treats held stock as unavailable', function () {
    seedConsumeScenario($this, BatchStatusEnum::Recalled, activeQty: 60, heldQty: 40);

    $ledger = app(StockLayerLedger::class);

    // On-hand layers total 100, but only 60 is in active lots, so 70 must fail.
    expect(fn () => $ledger->consume($this->company, $this->variant->id, $this->warehouse->id, 70, null))
        ->toThrow(ValidationException::class);
});

it('weighted-average auto-consume skips held layers', function () {
    $this->company->update(['inventory_costing_method' => InventoryCostingMethodEnum::WEIGHTED_AVERAGE]);
    $this->company->refresh();

    [$active, $expired] = seedConsumeScenario($this, BatchStatusEnum::Expired, activeQty: 60, heldQty: 40);

    $ledger = app(StockLayerLedger::class);
    $ledger->consume($this->company, $this->variant->id, $this->warehouse->id, 50, null);

    expect((int) StockLayer::withoutGlobalScopes()->where('batch_id', $active->id)->sum('qty_remaining'))->toBe(10);
    expect((int) StockLayer::withoutGlobalScopes()->where('batch_id', $expired->id)->sum('qty_remaining'))->toBe(40);
});

it('the reconciliation aligner never draws down held layers', function () {
    [$active, $recalled] = seedConsumeScenario($this, BatchStatusEnum::Recalled, activeQty: 60, heldQty: 40);

    // On-hand is overstated below the valued total: force a downward valued alignment.
    // valued (sum layers) = 100, on-hand = 80 -> aligner consumes 20, only from active.
    Stock::withoutGlobalScopes()->where('product_variant_id', $this->variant->id)->update(['quantity' => 80]);

    app(InventoryStockReconciliationAlignService::class)
        ->alignValuedQuantityToOnHand($this->company, $this->variant->id, $this->warehouse->id);

    expect((int) StockLayer::withoutGlobalScopes()->where('batch_id', $active->id)->sum('qty_remaining'))->toBe(40);
    expect((int) StockLayer::withoutGlobalScopes()->where('batch_id', $recalled->id)->sum('qty_remaining'))->toBe(40);
});

it('non-batch (null-batch) layers remain fully consumable', function () {
    // A plain layer with no batch must never be excluded by the held-lot filter.
    StockLayer::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'qty_remaining' => 25,
        'unit_cost' => 10,
        'received_at' => now(),
        'batch_id' => null,
    ]);

    Stock::withoutGlobalScopes()->create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 25,
        'on_hold' => 0,
    ]);

    $ledger = app(StockLayerLedger::class);
    $lines = $ledger->consume($this->company, $this->variant->id, $this->warehouse->id, 25, null);

    expect((int) collect($lines)->sum('quantity'))->toBe(25);
});
