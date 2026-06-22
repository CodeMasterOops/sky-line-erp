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

function fracWarmCache(): void
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
    fracWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Frac Mfg Co',
        'code' => 'FRAC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Frac Tester',
        'email' => 'frac-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Factory', 'code' => 'FACT',
    ]);

    // Raw material valued at £20/unit
    $rawProduct = Product::create(['company_id' => $this->company->id, 'name' => 'Flour', 'code' => 'FLOUR']);
    $this->rawVariant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $rawProduct->id,
        'sku' => 'FLOUR-1', 'is_default' => true,
        'purchase_price' => 20.0,
    ]);

    $fgProduct = Product::create(['company_id' => $this->company->id, 'name' => 'Cake', 'code' => 'CAKE']);
    $this->fgVariant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $fgProduct->id,
        'sku' => 'CAKE-1', 'is_default' => true,
    ]);

    $this->inventoryAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'Inventory', 'code' => 'INV-FRAC',
    ]);
    $this->wipAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'WIP', 'code' => 'WIP-FRAC',
    ]);
    $this->cogsAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'COGS', 'code' => 'COGS-FRAC',
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'inventory_account_id' => $this->inventoryAccount->id,
        'wip_account_id' => $this->wipAccount->id,
        'cogs_account_id' => $this->cogsAccount->id,
    ]);

    // BOM: 1.25 units of flour → 1 cake (fractional component quantity)
    $this->bom = Bom::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->fgVariant->id,
        'name' => 'Cake BOM v1',
        'version' => '1',
        'output_qty' => 1,
        'is_active' => true,
        'is_default' => true,
    ]);
    BomItem::create([
        'bom_id' => $this->bom->id,
        'product_variant_id' => $this->rawVariant->id,
        'quantity' => 1.25,
        'item_type' => 'material',
        'wastage_pct' => 0,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

function fracSeedRawStock(object $test, float $qty, float $unitCost): void
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

it('preserves fractional required quantity from the BOM when creating an order', function () {
    fracSeedRawStock($this, 10, 20.0);

    // plan 2 cakes × 1.25 flour = 2.5 flour required (integer truncation would yield 3)
    $response = $this->postJson('/api/admin/production-order', [
        'bom_id' => $this->bom->id,
        'warehouse_id' => $this->warehouse->id,
        'planned_qty' => 2,
    ]);

    $response->assertCreated();
    expect((float) $response->json('data.consumptions.0.required_qty'))->toBe(2.5);
});

it('consumes fractional raw material and values finished goods correctly on completion', function () {
    fracSeedRawStock($this, 10, 20.0);

    $response = $this->postJson('/api/admin/production-order', [
        'bom_id' => $this->bom->id,
        'warehouse_id' => $this->warehouse->id,
        'planned_qty' => 2,
    ]);
    $orderId = $response->json('data.id');
    $consumptionId = $response->json('data.consumptions.0.id');
    $this->postJson("/api/admin/production-order/{$orderId}/start")->assertOk();

    // consume 2.5 flour → 2 cakes; material cost = 2.5 × £20 = £50; FG unit cost = £25
    $this->postJson("/api/admin/production-order/{$orderId}/complete", [
        'produced_qty' => 2,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 2.5]],
    ])->assertOk();

    $order = ProductionOrder::findOrFail($orderId);
    expect((float) $order->consumptions()->first()->consumed_qty)->toBe(2.5);

    $rawStock = Stock::withoutGlobalScopes()
        ->where('product_variant_id', $this->rawVariant->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();
    // 10 - 2.5 consumed = 7.5 (truncation would leave 8)
    expect((float) $rawStock->quantity)->toBe(7.5);

    $fgMovement = StockMovement::withoutGlobalScopes()
        ->where('reference_type', ProductionOrder::class)
        ->where('reference_id', $orderId)
        ->where('type', ChangeTypeEnum::FINISHED_GOODS)
        ->first();
    expect((float) $fgMovement->unit_cost)->toBe(25.0);
    expect((float) $fgMovement->total_cost)->toBe(50.0);
});

it('allows a fractional produced quantity', function () {
    fracSeedRawStock($this, 10, 20.0);

    $response = $this->postJson('/api/admin/production-order', [
        'bom_id' => $this->bom->id,
        'warehouse_id' => $this->warehouse->id,
        'planned_qty' => 1,
    ]);
    $orderId = $response->json('data.id');
    $consumptionId = $response->json('data.consumptions.0.id');
    $this->postJson("/api/admin/production-order/{$orderId}/start")->assertOk();

    // produce 0.5 cake from 1.25 flour (£25 material); unit cost = 25 / 0.5 = £50
    // integer truncation would round produced_qty down to 0 and reject the order
    $this->postJson("/api/admin/production-order/{$orderId}/complete", [
        'produced_qty' => 0.5,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 1.25]],
    ])->assertOk();

    $fgMovement = StockMovement::withoutGlobalScopes()
        ->where('reference_type', ProductionOrder::class)
        ->where('reference_id', $orderId)
        ->where('type', ChangeTypeEnum::FINISHED_GOODS)
        ->first();

    expect((float) $fgMovement->quantity)->toBe(0.5);
    expect((float) $fgMovement->unit_cost)->toBe(50.0);
});
