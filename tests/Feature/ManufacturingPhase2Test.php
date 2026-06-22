<?php

use App\Models\Bom;
use App\Models\User;
use App\Models\Stock;
use App\Models\Account;
use App\Models\BomItem;
use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Models\StockLayer;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Enums\ChangeTypeEnum;
use App\Models\StockMovement;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Models\ProductionOrder;
use App\Models\StockAdjustment;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use App\Services\Inventory\InventoryLayerReceiptService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function p2WarmCache(): void
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
    p2WarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Phase2 Test Co',
        'code' => 'P2TC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Phase2 Tester',
        'email' => 'p2-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'P2 Factory', 'code' => 'P2F',
    ]);

    $rawProduct = Product::create(['company_id' => $this->company->id, 'name' => 'Wire', 'code' => 'WIRE']);
    $this->rawVariant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $rawProduct->id,
        'sku' => 'WIRE-1', 'is_default' => true,
        'purchase_price' => 10.0,
    ]);

    $fgProduct = Product::create(['company_id' => $this->company->id, 'name' => 'Coil', 'code' => 'COIL']);
    $this->fgVariant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $fgProduct->id,
        'sku' => 'COIL-1', 'is_default' => true,
    ]);

    $this->inventoryAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'P2 Inventory', 'code' => 'P2-INV',
    ]);
    $this->wipAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'P2 WIP', 'code' => 'P2-WIP',
    ]);
    $this->cogsAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'P2 COGS', 'code' => 'P2-COGS',
    ]);
    $this->varianceAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'P2 Mfg Variance', 'code' => 'P2-VAR',
    ]);
    $this->stockAdjAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'P2 Stock Adj', 'code' => 'P2-SADJ',
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'inventory_account_id' => $this->inventoryAccount->id,
        'wip_account_id' => $this->wipAccount->id,
        'cogs_account_id' => $this->cogsAccount->id,
        'manufacturing_variance_account_id' => $this->varianceAccount->id,
        'stock_adjustment_account_id' => $this->stockAdjAccount->id,
    ]);

    // BOM: 2 units of wire → 1 coil
    $this->bom = Bom::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->fgVariant->id,
        'name' => 'Coil BOM v1',
        'version' => '1',
        'output_qty' => 1,
        'is_active' => true,
        'is_default' => true,
    ]);
    BomItem::create([
        'bom_id' => $this->bom->id,
        'product_variant_id' => $this->rawVariant->id,
        'quantity' => 2,
        'item_type' => 'material',
        'wastage_pct' => 0,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

function p2SeedRawStock(object $test, int $qty, float $unitCost): void
{
    $adj = StockAdjustment::create([
        'company_id' => $test->company->id,
        'reference_no' => 'P2SEED-'.uniqid(),
        'date' => now()->toDateString(),
        'warehouse_id' => $test->warehouse->id,
        'create_user_id' => $test->user->id,
        'status' => 'approved',
    ]);

    $receipt = app(InventoryLayerReceiptService::class);
    DB::transaction(fn () => $receipt->receive(
        $test->company, $adj, $test->rawVariant->id,
        $test->warehouse->id, $qty, $unitCost,
        ChangeTypeEnum::PURCHASE, $test->user->id, null,
    ));
}

function p2CreateAndStartOrder(object $test, float $plannedQty = 1): ProductionOrder
{
    $response = $test->postJson('/api/admin/production-order', [
        'bom_id' => $test->bom->id,
        'warehouse_id' => $test->warehouse->id,
        'planned_qty' => $plannedQty,
    ]);
    $response->assertCreated();

    $orderId = $response->json('data.id');
    $test->postJson("/api/admin/production-order/{$orderId}/start")->assertOk();

    return ProductionOrder::findOrFail($orderId)->fresh();
}

// ─── wastage split ───────────────────────────────────────────────────────────

it('creates a WASTAGE movement when consumed exceeds required quantity', function () {
    // required_qty = 2 (BOM 2 wire/coil × 1 planned). Consume 3 → 1 excess = wastage.
    p2SeedRawStock($this, 10, 20.0);
    $order = p2CreateAndStartOrder($this, 1);
    $consumptionId = $order->consumptions()->first()->id;

    $this->postJson("/api/admin/production-order/{$order->id}/complete", [
        'produced_qty' => 1,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 3]],
    ])->assertOk();

    $wastageMovement = StockMovement::withoutGlobalScopes()
        ->where('reference_type', ProductionOrder::class)
        ->where('reference_id', $order->id)
        ->where('type', ChangeTypeEnum::WASTAGE)
        ->first();

    expect($wastageMovement)->not->toBeNull();
    expect((int) $wastageMovement->quantity)->toBe(1);
});

it('does not create a WASTAGE movement when consumed equals required quantity', function () {
    p2SeedRawStock($this, 10, 20.0);
    $order = p2CreateAndStartOrder($this, 1);
    $consumptionId = $order->consumptions()->first()->id;

    $this->postJson("/api/admin/production-order/{$order->id}/complete", [
        'produced_qty' => 1,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 2]],
    ])->assertOk();

    $wastageCount = StockMovement::withoutGlobalScopes()
        ->where('reference_type', ProductionOrder::class)
        ->where('reference_id', $order->id)
        ->where('type', ChangeTypeEnum::WASTAGE)
        ->count();

    expect($wastageCount)->toBe(0);
});

it('MANUFACTURING_ISSUE covers only required quantity when there is excess wastage', function () {
    // required = 2, consumed = 3 → MANUFACTURING_ISSUE should be 2, not 3
    p2SeedRawStock($this, 10, 20.0);
    $order = p2CreateAndStartOrder($this, 1);
    $consumptionId = $order->consumptions()->first()->id;

    $this->postJson("/api/admin/production-order/{$order->id}/complete", [
        'produced_qty' => 1,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 3]],
    ])->assertOk();

    $issueMovement = StockMovement::withoutGlobalScopes()
        ->where('reference_type', ProductionOrder::class)
        ->where('reference_id', $order->id)
        ->where('type', ChangeTypeEnum::MANUFACTURING_ISSUE)
        ->first();

    expect((int) $issueMovement->quantity)->toBe(2);
});

it('FG unit cost is based on required quantity only, excluding wastage cost', function () {
    // required = 2 @ £20 = £40 WIP. Wastage 1 @ £20 is expensed separately.
    // FG unit cost = £40 / 1 produced = £40
    p2SeedRawStock($this, 10, 20.0);
    $order = p2CreateAndStartOrder($this, 1);
    $consumptionId = $order->consumptions()->first()->id;

    $this->postJson("/api/admin/production-order/{$order->id}/complete", [
        'produced_qty' => 1,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 3]],
    ])->assertOk();

    $fgMovement = StockMovement::withoutGlobalScopes()
        ->where('reference_type', ProductionOrder::class)
        ->where('reference_id', $order->id)
        ->where('type', ChangeTypeEnum::FINISHED_GOODS)
        ->first();

    expect((float) $fgMovement->unit_cost)->toBe(40.0);
    expect((float) $fgMovement->total_cost)->toBe(40.0);
});

it('raw material stock deduction covers both required and wastage quantities', function () {
    // Seed 10, consume 3 total (2 issue + 1 wastage) → 7 remaining
    p2SeedRawStock($this, 10, 20.0);
    $order = p2CreateAndStartOrder($this, 1);
    $consumptionId = $order->consumptions()->first()->id;

    $this->postJson("/api/admin/production-order/{$order->id}/complete", [
        'produced_qty' => 1,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 3]],
    ])->assertOk();

    $rawStock = Stock::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('product_variant_id', $this->rawVariant->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->value('quantity');

    expect((int) $rawStock)->toBe(7);
});

// ─── wastage GL posting ───────────────────────────────────────────────────────

it('posts Manufacturing Variance Dr / Inventory Cr for WASTAGE', function () {
    p2SeedRawStock($this, 10, 20.0);
    $order = p2CreateAndStartOrder($this, 1);
    $consumptionId = $order->consumptions()->first()->id;

    $this->postJson("/api/admin/production-order/{$order->id}/complete", [
        'produced_qty' => 1,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 3]],
    ])->assertOk();

    $wastageMovement = StockMovement::withoutGlobalScopes()
        ->where('reference_type', ProductionOrder::class)
        ->where('reference_id', $order->id)
        ->where('type', ChangeTypeEnum::WASTAGE)
        ->first();

    $journal = $wastageMovement->glJournal()->withoutGlobalScopes()->first();
    expect($journal)->not->toBeNull();

    $items = $journal->journalItems()->withoutGlobalScopes()->get();
    $debit = $items->firstWhere('dr_amount', '>', 0);
    $credit = $items->firstWhere('cr_amount', '>', 0);

    expect($debit->account_id)->toBe($this->varianceAccount->id);
    expect($credit->account_id)->toBe($this->inventoryAccount->id);
    expect((float) $debit->dr_amount)->toBe(20.0); // 1 wire × £20
});

it('falls back to stock_adjustment account for WASTAGE when no variance account is configured', function () {
    AccountSetting::withoutGlobalScopes()->where('company_id', $this->company->id)->forceDelete();
    AccountSetting::create([
        'company_id' => $this->company->id,
        'inventory_account_id' => $this->inventoryAccount->id,
        'wip_account_id' => $this->wipAccount->id,
        'cogs_account_id' => $this->cogsAccount->id,
        'stock_adjustment_account_id' => $this->stockAdjAccount->id,
    ]);

    p2SeedRawStock($this, 10, 20.0);
    $order = p2CreateAndStartOrder($this, 1);
    $consumptionId = $order->consumptions()->first()->id;

    $this->postJson("/api/admin/production-order/{$order->id}/complete", [
        'produced_qty' => 1,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 3]],
    ])->assertOk();

    $wastageMovement = StockMovement::withoutGlobalScopes()
        ->where('reference_type', ProductionOrder::class)
        ->where('reference_id', $order->id)
        ->where('type', ChangeTypeEnum::WASTAGE)
        ->first();

    $journal = $wastageMovement->glJournal()->withoutGlobalScopes()->first();
    $debit = $journal->journalItems()->withoutGlobalScopes()->where('dr_amount', '>', 0)->first();

    expect($debit->account_id)->toBe($this->stockAdjAccount->id);
});

it('auto-posts GL journals for all three movement types when wastage occurs', function () {
    p2SeedRawStock($this, 10, 20.0);
    $order = p2CreateAndStartOrder($this, 1);
    $consumptionId = $order->consumptions()->first()->id;

    $this->postJson("/api/admin/production-order/{$order->id}/complete", [
        'produced_qty' => 1,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 3]],
    ])->assertOk();

    $movements = StockMovement::withoutGlobalScopes()
        ->where('reference_type', ProductionOrder::class)
        ->where('reference_id', $order->id)
        ->whereIn('type', [
            ChangeTypeEnum::MANUFACTURING_ISSUE,
            ChangeTypeEnum::WASTAGE,
            ChangeTypeEnum::FINISHED_GOODS,
        ])
        ->get();

    expect($movements)->toHaveCount(3);
    foreach ($movements as $movement) {
        expect($movement->gl_journal_id)->not->toBeNull();
    }
});

// ─── stock layer traceability ─────────────────────────────────────────────────

it('stamps source_production_order_id on the finished goods stock layer', function () {
    p2SeedRawStock($this, 10, 20.0);
    $order = p2CreateAndStartOrder($this, 1);
    $consumptionId = $order->consumptions()->first()->id;

    $this->postJson("/api/admin/production-order/{$order->id}/complete", [
        'produced_qty' => 1,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 2]],
    ])->assertOk();

    $fgLayer = StockLayer::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('product_variant_id', $this->fgVariant->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();

    expect($fgLayer)->not->toBeNull();
    expect($fgLayer->source_production_order_id)->toBe($order->id);
});

it('leaves source_production_order_id null on raw material layers', function () {
    p2SeedRawStock($this, 10, 20.0);

    $rawLayer = StockLayer::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('product_variant_id', $this->rawVariant->id)
        ->first();

    expect($rawLayer->source_production_order_id)->toBeNull();
});

// ─── production variance report ──────────────────────────────────────────────

it('production variance report returns rows for completed orders', function () {
    p2SeedRawStock($this, 10, 20.0);
    $order = p2CreateAndStartOrder($this, 1);
    $consumptionId = $order->consumptions()->first()->id;

    $this->postJson("/api/admin/production-order/{$order->id}/complete", [
        'produced_qty' => 1,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 3]],
    ])->assertOk();

    $response = $this->getJson('/api/admin/inventory-report/production-variance');

    $response->assertOk();
    $data = $response->json('data');

    expect($data['rows'])->toHaveCount(1);
    expect($data['rows'][0]['order_no'])->not->toBeEmpty();
    expect($data['rows'][0]['finished_product'])->toBe('Coil');
    expect((float) $data['rows'][0]['produced_qty'])->toBe(1.0);
});

it('production variance report calculates quantity variance correctly', function () {
    // required = 2 @ £20 → standard_cost = £40. actual = 3 @ £20 → actual_cost = £60. variance = £20.
    p2SeedRawStock($this, 10, 20.0);
    $order = p2CreateAndStartOrder($this, 1);
    $consumptionId = $order->consumptions()->first()->id;

    $this->postJson("/api/admin/production-order/{$order->id}/complete", [
        'produced_qty' => 1,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 3]],
    ])->assertOk();

    $response = $this->getJson('/api/admin/inventory-report/production-variance');
    $response->assertOk();

    $row = $response->json('data.rows.0');
    $component = $row['components'][0];

    expect((float) $component['required_qty'])->toBe(2.0);
    expect((float) $component['consumed_qty'])->toBe(3.0);
    expect((float) $component['standard_cost'])->toBe(40.0);
    expect((float) $component['actual_cost'])->toBe(60.0);
    expect((float) $component['variance'])->toBe(20.0);
    expect((float) $row['total_variance'])->toBe(20.0);
});

it('production variance report shows zero variance when consumption matches required', function () {
    p2SeedRawStock($this, 10, 20.0);
    $order = p2CreateAndStartOrder($this, 1);
    $consumptionId = $order->consumptions()->first()->id;

    $this->postJson("/api/admin/production-order/{$order->id}/complete", [
        'produced_qty' => 1,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 2]],
    ])->assertOk();

    $response = $this->getJson('/api/admin/inventory-report/production-variance');
    $response->assertOk();

    expect((float) $response->json('data.rows.0.total_variance'))->toBe(0.0);
});

it('production variance report summary aggregates correctly across multiple orders', function () {
    p2SeedRawStock($this, 20, 20.0);

    // Order 1: variance £20 (consume 3 instead of 2)
    $order1 = p2CreateAndStartOrder($this, 1);
    $cid1 = $order1->consumptions()->first()->id;
    $this->postJson("/api/admin/production-order/{$order1->id}/complete", [
        'produced_qty' => 1,
        'consumptions' => [['id' => $cid1, 'consumed_qty' => 3]],
    ])->assertOk();

    // Order 2: variance £0 (consume exactly required 2)
    $order2 = p2CreateAndStartOrder($this, 1);
    $cid2 = $order2->consumptions()->first()->id;
    $this->postJson("/api/admin/production-order/{$order2->id}/complete", [
        'produced_qty' => 1,
        'consumptions' => [['id' => $cid2, 'consumed_qty' => 2]],
    ])->assertOk();

    $response = $this->getJson('/api/admin/inventory-report/production-variance');
    $response->assertOk();

    $summary = $response->json('data.summary');
    expect($summary['total_orders'])->toBe(2);
    expect((float) $summary['total_variance'])->toBe(20.0);
});

it('production variance report returns empty when no completed orders exist', function () {
    $response = $this->getJson('/api/admin/inventory-report/production-variance');

    $response->assertOk();
    $data = $response->json('data');
    expect($data['rows'])->toBeEmpty();
    expect($data['summary']['total_orders'])->toBe(0);
});

it('production variance report filters rows by warehouse_id', function () {
    $otherWarehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Other Warehouse', 'code' => 'OTH',
    ]);

    p2SeedRawStock($this, 10, 20.0);
    $order = p2CreateAndStartOrder($this, 1);
    $cid = $order->consumptions()->first()->id;
    $this->postJson("/api/admin/production-order/{$order->id}/complete", [
        'produced_qty' => 1,
        'consumptions' => [['id' => $cid, 'consumed_qty' => 2]],
    ])->assertOk();

    // Other warehouse → no rows
    $this->getJson("/api/admin/inventory-report/production-variance?warehouse_id={$otherWarehouse->id}")
        ->assertOk()
        ->assertJsonPath('data.rows', []);

    // Correct warehouse → 1 row
    $response = $this->getJson("/api/admin/inventory-report/production-variance?warehouse_id={$this->warehouse->id}");
    $response->assertOk();
    expect($response->json('data.rows'))->toHaveCount(1);
});
