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
use App\Enums\StockDirectionEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use App\Services\Inventory\InventoryLayerReceiptService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function mfgWarmCache(): void
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
    mfgWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Mfg Test Co',
        'code' => 'MFG',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Mfg Tester',
        'email' => 'mfg-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Factory', 'code' => 'FACT',
    ]);

    // Raw material
    $rawProduct = Product::create(['company_id' => $this->company->id, 'name' => 'Steel Rod', 'code' => 'STEEL']);
    $this->rawVariant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $rawProduct->id,
        'sku' => 'STEEL-1', 'is_default' => true,
        'purchase_price' => 10.0,
    ]);

    // Finished goods
    $fgProduct = Product::create(['company_id' => $this->company->id, 'name' => 'Bolt', 'code' => 'BOLT']);
    $this->fgVariant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $fgProduct->id,
        'sku' => 'BOLT-1', 'is_default' => true,
    ]);

    // GL accounts
    $this->inventoryAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'Inventory', 'code' => 'INV-MFG',
    ]);
    $this->wipAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'WIP', 'code' => 'WIP-MFG',
    ]);
    $this->cogsAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'COGS', 'code' => 'COGS-MFG',
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'inventory_account_id' => $this->inventoryAccount->id,
        'wip_account_id' => $this->wipAccount->id,
        'cogs_account_id' => $this->cogsAccount->id,
    ]);

    // BOM: 2 units of steel rod → 1 bolt
    $this->bom = Bom::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->fgVariant->id,
        'name' => 'Bolt BOM v1',
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

// Seed raw material stock via the ledger using a StockAdjustment as the reference document
function seedRawStock(object $test, int $qty, float $unitCost): void
{
    $adj = StockAdjustment::create([
        'company_id' => $test->company->id,
        'reference_no' => 'SEED-'.uniqid(),
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

// Create and start a production order
function createAndStartOrder(object $test, float $plannedQty = 1): ProductionOrder
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

// ─── store ──────────────────────────────────────────────────────────────────

it('creates a production order with consumptions pre-populated from BOM', function () {
    seedRawStock($this, 10, 20.0); // 5 bolts × 2 rods each = 10 rods needed
    $response = $this->postJson('/api/admin/production-order', [
        'bom_id' => $this->bom->id,
        'warehouse_id' => $this->warehouse->id,
        'planned_qty' => 5,
    ]);

    $response->assertCreated();
    $data = $response->json('data');

    expect($data['status'])->toBe('draft');
    expect((float) $data['planned_qty'])->toBe(5.0);
    expect($data['consumptions'])->toHaveCount(1);
    // 2 rods/bolt × 5 bolts = 10 rods required
    expect((float) $data['consumptions'][0]['required_qty'])->toBe(10.0);
});

it('rejects store without required fields', function () {
    $this->postJson('/api/admin/production-order', [])->assertUnprocessable();
});

// ─── start ──────────────────────────────────────────────────────────────────

it('transitions status to in_progress on start', function () {
    seedRawStock($this, 2, 20.0);
    $response = $this->postJson('/api/admin/production-order', [
        'bom_id' => $this->bom->id,
        'warehouse_id' => $this->warehouse->id,
        'planned_qty' => 1,
    ]);
    $orderId = $response->json('data.id');

    $this->postJson("/api/admin/production-order/{$orderId}/start")->assertOk();

    expect(ProductionOrder::find($orderId)->status)->toBe('in_progress');
});

it('rejects starting an already in_progress order', function () {
    seedRawStock($this, 2, 20.0);
    $order = createAndStartOrder($this);

    $this->postJson("/api/admin/production-order/{$order->id}/start")->assertStatus(422);
});

// ─── complete — happy path ───────────────────────────────────────────────────

it('creates MANUFACTURING_ISSUE movement when completing production order', function () {
    seedRawStock($this, 10, 20.0);
    $order = createAndStartOrder($this, 1);
    $consumptionId = $order->consumptions()->first()->id;

    $this->postJson("/api/admin/production-order/{$order->id}/complete", [
        'produced_qty' => 1,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 2]],
    ])->assertOk();

    $issueMovement = StockMovement::withoutGlobalScopes()
        ->where('reference_type', ProductionOrder::class)
        ->where('reference_id', $order->id)
        ->where('type', ChangeTypeEnum::MANUFACTURING_ISSUE)
        ->first();

    expect($issueMovement)->not->toBeNull();
    expect($issueMovement->direction)->toBe(StockDirectionEnum::OUT);
    expect((int) $issueMovement->quantity)->toBe(2);
});

it('creates FINISHED_GOODS movement when completing production order', function () {
    seedRawStock($this, 10, 20.0);
    $order = createAndStartOrder($this, 1);
    $consumptionId = $order->consumptions()->first()->id;

    $this->postJson("/api/admin/production-order/{$order->id}/complete", [
        'produced_qty' => 1,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 2]],
    ])->assertOk();

    $fgMovement = StockMovement::withoutGlobalScopes()
        ->where('reference_type', ProductionOrder::class)
        ->where('reference_id', $order->id)
        ->where('type', ChangeTypeEnum::FINISHED_GOODS)
        ->first();

    expect($fgMovement)->not->toBeNull();
    expect($fgMovement->direction)->toBe(StockDirectionEnum::IN);
    expect((int) $fgMovement->quantity)->toBe(1);
});

it('deducts raw material stock on completion', function () {
    seedRawStock($this, 10, 20.0);
    $order = createAndStartOrder($this, 1);
    $consumptionId = $order->consumptions()->first()->id;

    $this->postJson("/api/admin/production-order/{$order->id}/complete", [
        'produced_qty' => 1,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 2]],
    ])->assertOk();

    $rawStock = Stock::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('product_variant_id', $this->rawVariant->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();

    expect((int) $rawStock->quantity)->toBe(8); // 10 - 2 consumed
});

it('adds finished goods stock on completion', function () {
    seedRawStock($this, 10, 20.0);
    $order = createAndStartOrder($this, 1);
    $consumptionId = $order->consumptions()->first()->id;

    $this->postJson("/api/admin/production-order/{$order->id}/complete", [
        'produced_qty' => 1,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 2]],
    ])->assertOk();

    $fgStock = Stock::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('product_variant_id', $this->fgVariant->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();

    expect((int) $fgStock->quantity)->toBe(1);
});

it('computes finished goods unit cost from consumed layers', function () {
    // 10 rods @ £20 each; consume 2 → £40 material cost; 1 bolt produced → unit cost £40
    seedRawStock($this, 10, 20.0);
    $order = createAndStartOrder($this, 1);
    $consumptionId = $order->consumptions()->first()->id;

    $this->postJson("/api/admin/production-order/{$order->id}/complete", [
        'produced_qty' => 1,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 2]],
    ])->assertOk();

    $fgMovement = StockMovement::withoutGlobalScopes()
        ->where('reference_type', ProductionOrder::class)
        ->where('reference_id', $order->id)
        ->where('type', ChangeTypeEnum::FINISHED_GOODS)
        ->first();

    expect((float) $fgMovement->unit_cost)->toBe(40.0);
    expect((float) $fgMovement->total_cost)->toBe(40.0);
});

it('creates a stock layer for finished goods with correct cost', function () {
    seedRawStock($this, 10, 20.0);
    $order = createAndStartOrder($this, 1);
    $consumptionId = $order->consumptions()->first()->id;

    $this->postJson("/api/admin/production-order/{$order->id}/complete", [
        'produced_qty' => 1,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 2]],
    ])->assertOk();

    $fgLayer = StockLayer::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('product_variant_id', $this->fgVariant->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->where('qty_remaining', '>', 0)
        ->first();

    expect($fgLayer)->not->toBeNull();
    expect((float) $fgLayer->unit_cost)->toBe(40.0);
    expect((int) $fgLayer->qty_remaining)->toBe(1);
});

it('records unit_cost on the consumption record', function () {
    seedRawStock($this, 10, 20.0);
    $order = createAndStartOrder($this, 1);
    $consumption = $order->consumptions()->first();

    $this->postJson("/api/admin/production-order/{$order->id}/complete", [
        'produced_qty' => 1,
        'consumptions' => [['id' => $consumption->id, 'consumed_qty' => 2]],
    ])->assertOk();

    expect((float) $consumption->fresh()->unit_cost)->toBe(20.0);
    expect((float) $consumption->fresh()->consumed_qty)->toBe(2.0);
});

it('sets production order status to completed with actual_end timestamp', function () {
    seedRawStock($this, 10, 20.0);
    $order = createAndStartOrder($this, 1);
    $consumptionId = $order->consumptions()->first()->id;

    $this->postJson("/api/admin/production-order/{$order->id}/complete", [
        'produced_qty' => 1,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 2]],
    ])->assertOk();

    $order->refresh();
    expect($order->status)->toBe('completed');
    expect($order->actual_end)->not->toBeNull();
    expect($order->approve_user_id)->toBe($this->user->id);
});

// ─── complete — GL posting ────────────────────────────────────────────────────

it('auto-posts GL journals for MANUFACTURING_ISSUE and FINISHED_GOODS', function () {
    seedRawStock($this, 10, 20.0);
    $order = createAndStartOrder($this, 1);
    $consumptionId = $order->consumptions()->first()->id;

    $this->postJson("/api/admin/production-order/{$order->id}/complete", [
        'produced_qty' => 1,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 2]],
    ])->assertOk();

    $movements = StockMovement::withoutGlobalScopes()
        ->where('reference_type', ProductionOrder::class)
        ->where('reference_id', $order->id)
        ->whereIn('type', [ChangeTypeEnum::MANUFACTURING_ISSUE, ChangeTypeEnum::FINISHED_GOODS])
        ->get();

    foreach ($movements as $movement) {
        expect($movement->gl_journal_id)->not->toBeNull();
    }
});

it('posts WIP Dr / Inventory Cr for MANUFACTURING_ISSUE', function () {
    seedRawStock($this, 10, 20.0);
    $order = createAndStartOrder($this, 1);
    $consumptionId = $order->consumptions()->first()->id;

    $this->postJson("/api/admin/production-order/{$order->id}/complete", [
        'produced_qty' => 1,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 2]],
    ])->assertOk();

    $issueMovement = StockMovement::withoutGlobalScopes()
        ->where('reference_type', ProductionOrder::class)
        ->where('reference_id', $order->id)
        ->where('type', ChangeTypeEnum::MANUFACTURING_ISSUE)
        ->first();

    $journal = $issueMovement->glJournal()->withoutGlobalScopes()->first();
    expect($journal)->not->toBeNull();

    $items = $journal->journalItems()->withoutGlobalScopes()->get();
    $debit = $items->firstWhere('dr_amount', '>', 0);
    $credit = $items->firstWhere('cr_amount', '>', 0);

    expect($debit->account_id)->toBe($this->wipAccount->id);
    expect($credit->account_id)->toBe($this->inventoryAccount->id);
    expect((float) $debit->dr_amount)->toBe(40.0); // 2 × £20
});

it('posts Inventory Dr / WIP Cr for FINISHED_GOODS', function () {
    seedRawStock($this, 10, 20.0);
    $order = createAndStartOrder($this, 1);
    $consumptionId = $order->consumptions()->first()->id;

    $this->postJson("/api/admin/production-order/{$order->id}/complete", [
        'produced_qty' => 1,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 2]],
    ])->assertOk();

    $fgMovement = StockMovement::withoutGlobalScopes()
        ->where('reference_type', ProductionOrder::class)
        ->where('reference_id', $order->id)
        ->where('type', ChangeTypeEnum::FINISHED_GOODS)
        ->first();

    $journal = $fgMovement->glJournal()->withoutGlobalScopes()->first();
    expect($journal)->not->toBeNull();

    $items = $journal->journalItems()->withoutGlobalScopes()->get();
    $debit = $items->firstWhere('dr_amount', '>', 0);
    $credit = $items->firstWhere('cr_amount', '>', 0);

    expect($debit->account_id)->toBe($this->inventoryAccount->id);
    expect($credit->account_id)->toBe($this->wipAccount->id);
    expect((float) $debit->dr_amount)->toBe(40.0);
});

it('falls back to COGS account when no WIP account is configured', function () {
    // forceDelete required because AccountSetting uses SoftDeletes
    AccountSetting::withoutGlobalScopes()->where('company_id', $this->company->id)->forceDelete();
    AccountSetting::create([
        'company_id' => $this->company->id,
        'inventory_account_id' => $this->inventoryAccount->id,
        'cogs_account_id' => $this->cogsAccount->id,
    ]);

    seedRawStock($this, 10, 20.0);
    $order = createAndStartOrder($this, 1);
    $consumptionId = $order->consumptions()->first()->id;

    $this->postJson("/api/admin/production-order/{$order->id}/complete", [
        'produced_qty' => 1,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 2]],
    ])->assertOk();

    $issueMovement = StockMovement::withoutGlobalScopes()
        ->where('reference_type', ProductionOrder::class)
        ->where('reference_id', $order->id)
        ->where('type', ChangeTypeEnum::MANUFACTURING_ISSUE)
        ->first();

    $journal = $issueMovement->glJournal()->withoutGlobalScopes()->first();
    $debit = $journal->journalItems()->withoutGlobalScopes()->where('dr_amount', '>', 0)->first();

    expect($debit->account_id)->toBe($this->cogsAccount->id);
});

// ─── complete — FIFO cost preservation ───────────────────────────────────────

it('preserves FIFO cost in finished goods layer across multiple raw material layers', function () {
    seedRawStock($this, 5, 10.0); // 5 @ £10
    seedRawStock($this, 5, 20.0); // 5 @ £20

    // Produce 3 bolts, each needing 2 rods (= 6 rods total)
    $order = createAndStartOrder($this, 3);
    $consumptionId = $order->consumptions()->first()->id;

    // FIFO: 5 @ £10 + 1 @ £20 = £70 for 6 rods; 3 bolts → unit cost £70/3
    $this->postJson("/api/admin/production-order/{$order->id}/complete", [
        'produced_qty' => 3,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 6]],
    ])->assertOk();

    $fgMovement = StockMovement::withoutGlobalScopes()
        ->where('reference_type', ProductionOrder::class)
        ->where('reference_id', $order->id)
        ->where('type', ChangeTypeEnum::FINISHED_GOODS)
        ->first();

    // total_cost may differ by fractions of a penny due to dividing then
    // multiplying a repeating decimal; round to 2dp for the assertion.
    expect(round((float) $fgMovement->total_cost, 2))->toBe(70.0);
    expect((float) $fgMovement->unit_cost)->toBe(round(70 / 3, 4));
});

// ─── complete — error paths ──────────────────────────────────────────────────

it('rejects completing a draft order', function () {
    seedRawStock($this, 2, 20.0);
    $response = $this->postJson('/api/admin/production-order', [
        'bom_id' => $this->bom->id,
        'warehouse_id' => $this->warehouse->id,
        'planned_qty' => 1,
    ]);
    $orderId = $response->json('data.id');

    $this->postJson("/api/admin/production-order/{$orderId}/complete", [
        'produced_qty' => 1,
    ])->assertStatus(422);
});

it('rejects completing an already-completed order', function () {
    seedRawStock($this, 10, 20.0);
    $order = createAndStartOrder($this, 1);
    $consumptionId = $order->consumptions()->first()->id;

    $payload = [
        'produced_qty' => 1,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 2]],
    ];

    $this->postJson("/api/admin/production-order/{$order->id}/complete", $payload)->assertOk();
    $this->postJson("/api/admin/production-order/{$order->id}/complete", $payload)->assertStatus(422);
});

it('rejects completion when raw material stock is insufficient', function () {
    seedRawStock($this, 2, 20.0); // 2 rods reserved by the order; try to consume 3 → insufficient layers
    $order = createAndStartOrder($this, 1);
    $consumptionId = $order->consumptions()->first()->id;

    $this->postJson("/api/admin/production-order/{$order->id}/complete", [
        'produced_qty' => 1,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 3]], // exceeds 2 available layers
    ])->assertUnprocessable();
});

it('rejects completion with a consumption ID that does not belong to this order', function () {
    seedRawStock($this, 10, 20.0);
    $order = createAndStartOrder($this, 1);

    $this->postJson("/api/admin/production-order/{$order->id}/complete", [
        'produced_qty' => 1,
        'consumptions' => [['id' => 99999, 'consumed_qty' => 2]],
    ])->assertStatus(422);
});

// ─── cancel ──────────────────────────────────────────────────────────────────

it('can cancel a draft order', function () {
    seedRawStock($this, 2, 20.0);
    $response = $this->postJson('/api/admin/production-order', [
        'bom_id' => $this->bom->id,
        'warehouse_id' => $this->warehouse->id,
        'planned_qty' => 1,
    ]);
    $orderId = $response->json('data.id');

    $this->postJson("/api/admin/production-order/{$orderId}/cancel")->assertOk();
    expect(ProductionOrder::find($orderId)->status)->toBe('cancelled');
});

it('can cancel an in_progress order', function () {
    seedRawStock($this, 2, 20.0);
    $order = createAndStartOrder($this, 1);

    $this->postJson("/api/admin/production-order/{$order->id}/cancel")->assertOk();
    expect($order->fresh()->status)->toBe('cancelled');
});

it('cannot cancel a completed order', function () {
    seedRawStock($this, 10, 20.0);
    $order = createAndStartOrder($this, 1);
    $consumptionId = $order->consumptions()->first()->id;

    $this->postJson("/api/admin/production-order/{$order->id}/complete", [
        'produced_qty' => 1,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 2]],
    ])->assertOk();

    $this->postJson("/api/admin/production-order/{$order->id}/cancel")->assertStatus(422);
});

it('cannot cancel an already-cancelled order', function () {
    seedRawStock($this, 2, 20.0);
    $response = $this->postJson('/api/admin/production-order', [
        'bom_id' => $this->bom->id,
        'warehouse_id' => $this->warehouse->id,
        'planned_qty' => 1,
    ]);
    $orderId = $response->json('data.id');

    $this->postJson("/api/admin/production-order/{$orderId}/cancel")->assertOk();
    $this->postJson("/api/admin/production-order/{$orderId}/cancel")->assertStatus(422);
});

// ─── weighted average costing ─────────────────────────────────────────────────

it('computes correct FG unit cost under weighted average costing', function () {
    $this->company->update(['inventory_costing_method' => InventoryCostingMethodEnum::WEIGHTED_AVERAGE]);

    seedRawStock($this, 4, 10.0); // 4 @ £10
    seedRawStock($this, 4, 20.0); // WA merges: (4×10 + 4×20) / 8 = £15

    $order = createAndStartOrder($this, 1);
    $consumptionId = $order->consumptions()->first()->id;

    // Consume 2 rods @ WA £15 = £30 → 1 bolt @ £30
    $this->postJson("/api/admin/production-order/{$order->id}/complete", [
        'produced_qty' => 1,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 2]],
    ])->assertOk();

    $fgMovement = StockMovement::withoutGlobalScopes()
        ->where('reference_type', ProductionOrder::class)
        ->where('reference_id', $order->id)
        ->where('type', ChangeTypeEnum::FINISHED_GOODS)
        ->first();

    expect((float) $fgMovement->unit_cost)->toBe(30.0);
});
