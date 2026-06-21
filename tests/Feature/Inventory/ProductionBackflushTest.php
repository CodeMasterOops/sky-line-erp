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

function backflushWarmCache(): void
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
    backflushWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);
    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Backflush Co', 'code' => 'BF',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);
    $this->user = User::create([
        'company_id' => $this->company->id, 'name' => 'BF Tester',
        'email' => 'bf-'.uniqid().'@example.com',
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

    $inv = Account::create(['company_id' => $this->company->id, 'account_group_id' => null, 'name' => 'Inventory', 'code' => 'INV-BF']);
    $wip = Account::create(['company_id' => $this->company->id, 'account_group_id' => null, 'name' => 'WIP', 'code' => 'WIP-BF']);
    $cogs = Account::create(['company_id' => $this->company->id, 'account_group_id' => null, 'name' => 'COGS', 'code' => 'COGS-BF']);
    AccountSetting::create([
        'company_id' => $this->company->id,
        'inventory_account_id' => $inv->id, 'wip_account_id' => $wip->id, 'cogs_account_id' => $cogs->id,
    ]);

    // Backflush BOM: 2 wire → 1 coil
    $this->bom = Bom::create([
        'company_id' => $this->company->id, 'product_variant_id' => $this->fgVariant->id,
        'name' => 'Coil BOM', 'version' => '1', 'output_qty' => 1,
        'is_active' => true, 'is_default' => true, 'is_backflush' => true,
    ]);
    BomItem::create([
        'bom_id' => $this->bom->id, 'product_variant_id' => $this->rawVariant->id,
        'quantity' => 2, 'item_type' => 'material', 'wastage_pct' => 0,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

function backflushSeedRawStock(object $test, float $qty, float $unitCost): void
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

it('auto-consumes materials at standard when completing a backflush order without consumptions', function () {
    backflushSeedRawStock($this, 20, 20.0);
    $res = $this->postJson('/api/admin/production-order', [
        'bom_id' => $this->bom->id, 'warehouse_id' => $this->warehouse->id, 'planned_qty' => 3,
    ]);
    $orderId = $res->json('data.id');
    $this->postJson("/api/admin/production-order/{$orderId}/start")->assertOk();

    // No consumptions supplied — backflush derives 2 wire × 3 coils = 6
    $this->postJson("/api/admin/production-order/{$orderId}/complete", [
        'produced_qty' => 3,
    ])->assertOk();

    $order = ProductionOrder::findOrFail($orderId);
    expect((float) $order->consumptions()->first()->consumed_qty)->toBe(6.0);

    $issue = StockMovement::withoutGlobalScopes()
        ->where('reference_type', ProductionOrder::class)->where('reference_id', $orderId)
        ->where('type', ChangeTypeEnum::MANUFACTURING_ISSUE)->first();
    expect((float) $issue->quantity)->toBe(6.0);

    $rawStock = Stock::withoutGlobalScopes()
        ->where('product_variant_id', $this->rawVariant->id)
        ->where('warehouse_id', $this->warehouse->id)->first();
    expect((float) $rawStock->quantity)->toBe(14.0); // 20 - 6
});

it('scales backflush consumption to a partial batch', function () {
    backflushSeedRawStock($this, 20, 20.0);
    $res = $this->postJson('/api/admin/production-order', [
        'bom_id' => $this->bom->id, 'warehouse_id' => $this->warehouse->id, 'planned_qty' => 4,
    ]);
    $orderId = $res->json('data.id');
    $this->postJson("/api/admin/production-order/{$orderId}/start")->assertOk();

    // Produce 1 of 4 → backflush 2 × (1/4 × 4) ... ratio = unitsMade/planned = 1/4 of required(8) = 2
    $this->postJson("/api/admin/production-order/{$orderId}/complete", [
        'produced_qty' => 1, 'close' => false,
    ])->assertOk();

    $order = ProductionOrder::findOrFail($orderId);
    expect((float) $order->consumptions()->first()->consumed_qty)->toBe(2.0);
    expect($order->status)->toBe('in_progress');
});
