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
use App\Models\JournalItem;
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

function scrapWarmCache(): void
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
    scrapWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);
    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Scrap Mfg Co', 'code' => 'SCRP',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);
    $this->user = User::create([
        'company_id' => $this->company->id, 'name' => 'Scrap Tester',
        'email' => 'scrap-'.uniqid().'@example.com',
        'password' => bcrypt('password'), 'user_type' => UserTypeEnum::ADMIN,
    ]);
    $this->warehouse = Warehouse::create(['company_id' => $this->company->id, 'name' => 'Factory', 'code' => 'FACT']);

    $rawProduct = Product::create(['company_id' => $this->company->id, 'name' => 'Plastic', 'code' => 'PLAS']);
    $this->rawVariant = ProductVariant::create([
        'company_id' => $this->company->id, 'product_id' => $rawProduct->id,
        'sku' => 'PLAS-1', 'is_default' => true, 'purchase_price' => 10.0,
    ]);
    $fgProduct = Product::create(['company_id' => $this->company->id, 'name' => 'Toy', 'code' => 'TOY']);
    $this->fgVariant = ProductVariant::create([
        'company_id' => $this->company->id, 'product_id' => $fgProduct->id,
        'sku' => 'TOY-1', 'is_default' => true,
    ]);

    $this->inventoryAccount = Account::create(['company_id' => $this->company->id, 'account_group_id' => null, 'name' => 'Inventory', 'code' => 'INV-S']);
    $this->wipAccount = Account::create(['company_id' => $this->company->id, 'account_group_id' => null, 'name' => 'WIP', 'code' => 'WIP-S']);
    $this->cogsAccount = Account::create(['company_id' => $this->company->id, 'account_group_id' => null, 'name' => 'COGS', 'code' => 'COGS-S']);
    $this->varianceAccount = Account::create(['company_id' => $this->company->id, 'account_group_id' => null, 'name' => 'Mfg Variance', 'code' => 'VAR-S']);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'inventory_account_id' => $this->inventoryAccount->id,
        'wip_account_id' => $this->wipAccount->id,
        'cogs_account_id' => $this->cogsAccount->id,
        'manufacturing_variance_account_id' => $this->varianceAccount->id,
    ]);

    // BOM: 2 plastic → 1 toy
    $this->bom = Bom::create([
        'company_id' => $this->company->id, 'product_variant_id' => $this->fgVariant->id,
        'name' => 'Toy BOM', 'version' => '1', 'output_qty' => 1, 'is_active' => true, 'is_default' => true,
    ]);
    BomItem::create([
        'bom_id' => $this->bom->id, 'product_variant_id' => $this->rawVariant->id,
        'quantity' => 2, 'item_type' => 'material', 'wastage_pct' => 0,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

function scrapSeedRawStock(object $test, float $qty, float $unitCost): void
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

/**
 * Produce $good good toys + $scrap defective, consuming $consume plastic. Returns the order.
 */
function scrapCompleteOrder(object $test, float $good, float $scrap, float $consume): ProductionOrder
{
    scrapSeedRawStock($test, 100, 10.0);
    $res = $test->postJson('/api/admin/production-order', [
        'bom_id' => $test->bom->id, 'warehouse_id' => $test->warehouse->id, 'planned_qty' => $good + $scrap,
    ]);
    $orderId = $res->json('data.id');
    $consumptionId = $res->json('data.consumptions.0.id');
    $test->postJson("/api/admin/production-order/{$orderId}/start")->assertOk();

    $test->postJson("/api/admin/production-order/{$orderId}/complete", [
        'produced_qty' => $good,
        'scrap_qty' => $scrap,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => $consume]],
    ])->assertOk();

    return ProductionOrder::findOrFail($orderId);
}

it('records scrapped quantity on the order', function () {
    // make 8 good + 2 scrap from 20 plastic
    $order = scrapCompleteOrder($this, 8, 2, 20);

    expect((float) $order->produced_qty)->toBe(8.0);
    expect((float) $order->scrapped_qty)->toBe(2.0);
});

it('values good finished goods at cost per unit made and adds only good units to stock', function () {
    // 20 plastic @ £10 = £200 over 10 units made → £20/unit; 8 good in stock
    $order = scrapCompleteOrder($this, 8, 2, 20);

    $fgMovement = StockMovement::withoutGlobalScopes()
        ->where('reference_type', ProductionOrder::class)->where('reference_id', $order->id)
        ->where('type', ChangeTypeEnum::FINISHED_GOODS)->first();
    expect((float) $fgMovement->unit_cost)->toBe(20.0);
    expect((float) $fgMovement->quantity)->toBe(8.0);

    $fgStock = Stock::withoutGlobalScopes()
        ->where('product_variant_id', $this->fgVariant->id)
        ->where('warehouse_id', $this->warehouse->id)->first();
    expect((float) $fgStock->quantity)->toBe(8.0);
});

it('expenses scrap cost to the manufacturing variance account', function () {
    $order = scrapCompleteOrder($this, 8, 2, 20);

    // scrap 2 units × £20 = £40 expensed: Variance Dr / WIP Cr
    $varianceDr = JournalItem::query()->where('account_id', $this->varianceAccount->id)->sum('dr_amount');
    expect(round((float) $varianceDr, 2))->toBe(40.0);
});

it('nets the WIP account to zero with scrap', function () {
    $order = scrapCompleteOrder($this, 8, 2, 20);

    $wipDr = JournalItem::query()->where('account_id', $this->wipAccount->id)->sum('dr_amount');
    $wipCr = JournalItem::query()->where('account_id', $this->wipAccount->id)->sum('cr_amount');

    // WIP Dr: material 200; WIP Cr: FG 160 + scrap 40 = 200
    expect(round((float) $wipDr, 2))->toBe(200.0);
    expect(round((float) $wipCr, 2))->toBe(200.0);
});

it('reports scrapped quantity and yield in the production variance report', function () {
    scrapCompleteOrder($this, 8, 2, 20);

    $response = $this->getJson('/api/admin/inventory-report/production-variance');
    $response->assertSuccessful();

    $row = collect($response->json('data.rows'))->first();
    expect((float) $row['scrapped_qty'])->toBe(2.0);
    expect((float) $row['yield_pct'])->toBe(80.0); // 8 / 10
    expect((float) $response->json('data.summary.total_scrapped'))->toBe(2.0);
});
