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

function partialWarmCache(): void
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
    partialWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);
    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Partial Mfg Co', 'code' => 'PART',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);
    $this->user = User::create([
        'company_id' => $this->company->id, 'name' => 'Partial Tester',
        'email' => 'partial-'.uniqid().'@example.com',
        'password' => bcrypt('password'), 'user_type' => UserTypeEnum::ADMIN,
    ]);
    $this->warehouse = Warehouse::create(['company_id' => $this->company->id, 'name' => 'Factory', 'code' => 'FACT']);

    $rawProduct = Product::create(['company_id' => $this->company->id, 'name' => 'Wire', 'code' => 'WIRE']);
    $this->rawVariant = ProductVariant::create([
        'company_id' => $this->company->id, 'product_id' => $rawProduct->id,
        'sku' => 'WIRE-1', 'is_default' => true, 'purchase_price' => 20.0,
    ]);
    $fgProduct = Product::create(['company_id' => $this->company->id, 'name' => 'Coil', 'code' => 'COIL']);
    $this->fgVariant = ProductVariant::create([
        'company_id' => $this->company->id, 'product_id' => $fgProduct->id,
        'sku' => 'COIL-1', 'is_default' => true,
    ]);

    $this->inventoryAccount = Account::create(['company_id' => $this->company->id, 'account_group_id' => null, 'name' => 'Inventory', 'code' => 'INV-P']);
    $this->wipAccount = Account::create(['company_id' => $this->company->id, 'account_group_id' => null, 'name' => 'WIP', 'code' => 'WIP-P']);
    $this->cogsAccount = Account::create(['company_id' => $this->company->id, 'account_group_id' => null, 'name' => 'COGS', 'code' => 'COGS-P']);
    AccountSetting::create([
        'company_id' => $this->company->id,
        'inventory_account_id' => $this->inventoryAccount->id,
        'wip_account_id' => $this->wipAccount->id,
        'cogs_account_id' => $this->cogsAccount->id,
    ]);

    // BOM: 2 wire → 1 coil
    $this->bom = Bom::create([
        'company_id' => $this->company->id, 'product_variant_id' => $this->fgVariant->id,
        'name' => 'Coil BOM', 'version' => '1', 'output_qty' => 1, 'is_active' => true, 'is_default' => true,
    ]);
    BomItem::create([
        'bom_id' => $this->bom->id, 'product_variant_id' => $this->rawVariant->id,
        'quantity' => 2, 'item_type' => 'material', 'wastage_pct' => 0,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

function partialSeedRawStock(object $test, float $qty, float $unitCost): void
{
    $adj = StockAdjustment::create([
        'company_id' => $test->company->id, 'reference_no' => 'SEED-'.uniqid(),
        'date' => now()->toDateString(), 'warehouse_id' => $test->warehouse->id,
        'create_user_id' => $test->user->id, 'status' => 'approved',
    ]);
    $receipt = app(InventoryLayerReceiptService::class);
    DB::transaction(fn () => $receipt->receive(
        $test->company, $adj, $test->rawVariant->id, $test->warehouse->id,
        $qty, $unitCost, ChangeTypeEnum::PURCHASE, $test->user->id, null,
    ));
}

function partialStartOrder(object $test, float $plannedQty): array
{
    $res = $test->postJson('/api/admin/production-order', [
        'bom_id' => $test->bom->id, 'warehouse_id' => $test->warehouse->id, 'planned_qty' => $plannedQty,
    ]);
    $orderId = $res->json('data.id');
    $consumptionId = $res->json('data.consumptions.0.id');
    $test->postJson("/api/admin/production-order/{$orderId}/start")->assertOk();

    return [$orderId, $consumptionId];
}

it('keeps the order open and accumulates totals on a partial completion', function () {
    partialSeedRawStock($this, 20, 20.0);
    [$orderId, $consumptionId] = partialStartOrder($this, 5);

    // First batch: produce 2 of 5, consume 4 wire, keep open
    $res = $this->postJson("/api/admin/production-order/{$orderId}/complete", [
        'produced_qty' => 2,
        'close' => false,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 4]],
    ]);
    $res->assertOk();
    expect($res->json('data.status'))->toBe('in_progress');

    $order = ProductionOrder::findOrFail($orderId);
    expect((float) $order->produced_qty)->toBe(2.0);
    expect((float) $order->consumptions()->first()->consumed_qty)->toBe(4.0);
});

it('completes an order across two batches with correct incremental stock and GL', function () {
    partialSeedRawStock($this, 20, 20.0);
    [$orderId, $consumptionId] = partialStartOrder($this, 5);

    // Batch 1: 2 coils, 4 wire, stay open
    $this->postJson("/api/admin/production-order/{$orderId}/complete", [
        'produced_qty' => 2, 'close' => false,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 4]],
    ])->assertOk();

    // Batch 2: 3 coils, 6 wire, close
    $final = $this->postJson("/api/admin/production-order/{$orderId}/complete", [
        'produced_qty' => 3, 'close' => true,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 6]],
    ]);
    $final->assertOk();
    expect($final->json('data.status'))->toBe('completed');

    $order = ProductionOrder::findOrFail($orderId);
    expect((float) $order->produced_qty)->toBe(5.0);

    // Two FG receipts (2 + 3 = 5 coils)
    $fgIn = StockMovement::withoutGlobalScopes()
        ->where('reference_type', ProductionOrder::class)->where('reference_id', $orderId)
        ->where('type', ChangeTypeEnum::FINISHED_GOODS)->sum('quantity');
    expect((float) $fgIn)->toBe(5.0);

    $fgStock = Stock::withoutGlobalScopes()
        ->where('product_variant_id', $this->fgVariant->id)
        ->where('warehouse_id', $this->warehouse->id)->first();
    expect((float) $fgStock->quantity)->toBe(5.0);

    // Raw consumed: 4 + 6 = 10 wire → 20 - 10 = 10 remaining
    $rawStock = Stock::withoutGlobalScopes()
        ->where('product_variant_id', $this->rawVariant->id)
        ->where('warehouse_id', $this->warehouse->id)->first();
    expect((float) $rawStock->quantity)->toBe(10.0);

    // Two MANUFACTURING_ISSUE movements (one per batch), each GL-posted
    $issues = StockMovement::withoutGlobalScopes()
        ->where('reference_type', ProductionOrder::class)->where('reference_id', $orderId)
        ->where('type', ChangeTypeEnum::MANUFACTURING_ISSUE)->get();
    expect($issues)->toHaveCount(2);
    $issues->each(fn ($m) => expect($m->gl_journal_id)->not->toBeNull());
});

it('rejects further completion after the order is closed', function () {
    partialSeedRawStock($this, 20, 20.0);
    [$orderId, $consumptionId] = partialStartOrder($this, 5);

    $this->postJson("/api/admin/production-order/{$orderId}/complete", [
        'produced_qty' => 2, 'close' => true,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 4]],
    ])->assertOk();

    $this->postJson("/api/admin/production-order/{$orderId}/complete", [
        'produced_qty' => 1, 'close' => true,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 2]],
    ])->assertStatus(422);
});

it('holds material reservations until the order is closed', function () {
    partialSeedRawStock($this, 20, 20.0);
    [$orderId, $consumptionId] = partialStartOrder($this, 5);

    $reservableType = (new ProductionOrder)->getMorphClass();

    // After a partial completion the reservation is still held
    $this->postJson("/api/admin/production-order/{$orderId}/complete", [
        'produced_qty' => 2, 'close' => false,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 4]],
    ])->assertOk();

    $openReservations = DB::table('stock_reservations')
        ->where('reservable_type', $reservableType)
        ->where('reservable_id', $orderId)
        ->whereNull('released_at')
        ->count();
    expect($openReservations)->toBeGreaterThan(0);

    // After closing, reservations are released
    $this->postJson("/api/admin/production-order/{$orderId}/complete", [
        'produced_qty' => 3, 'close' => true,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 6]],
    ])->assertOk();

    $stillOpen = DB::table('stock_reservations')
        ->where('reservable_type', $reservableType)
        ->where('reservable_id', $orderId)
        ->whereNull('released_at')
        ->count();
    expect($stillOpen)->toBe(0);
});
