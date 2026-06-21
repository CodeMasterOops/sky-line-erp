<?php

use App\Models\Bom;
use App\Models\User;
use App\Models\Account;
use App\Models\BomItem;
use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\JournalItem;
use App\Models\BomOperation;
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

function opCostWarmCache(): void
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
    opCostWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);
    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'OpCost Co', 'code' => 'OPC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);
    $this->user = User::create([
        'company_id' => $this->company->id, 'name' => 'OpCost Tester',
        'email' => 'opcost-'.uniqid().'@example.com',
        'password' => bcrypt('password'), 'user_type' => UserTypeEnum::ADMIN,
    ]);
    $this->warehouse = Warehouse::create(['company_id' => $this->company->id, 'name' => 'Factory', 'code' => 'FACT']);

    $rawProduct = Product::create(['company_id' => $this->company->id, 'name' => 'Sheet', 'code' => 'SHEET']);
    $this->rawVariant = ProductVariant::create([
        'company_id' => $this->company->id, 'product_id' => $rawProduct->id,
        'sku' => 'SHEET-1', 'is_default' => true, 'purchase_price' => 20.0,
    ]);
    $fgProduct = Product::create(['company_id' => $this->company->id, 'name' => 'Panel', 'code' => 'PANEL']);
    $this->fgVariant = ProductVariant::create([
        'company_id' => $this->company->id, 'product_id' => $fgProduct->id,
        'sku' => 'PANEL-1', 'is_default' => true,
    ]);

    $this->inventoryAccount = Account::create(['company_id' => $this->company->id, 'account_group_id' => null, 'name' => 'Inventory', 'code' => 'INV-OC']);
    $this->wipAccount = Account::create(['company_id' => $this->company->id, 'account_group_id' => null, 'name' => 'WIP', 'code' => 'WIP-OC']);
    $this->cogsAccount = Account::create(['company_id' => $this->company->id, 'account_group_id' => null, 'name' => 'COGS', 'code' => 'COGS-OC']);
    $this->overheadAccount = Account::create(['company_id' => $this->company->id, 'account_group_id' => null, 'name' => 'Overhead Applied', 'code' => 'OH-OC']);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'inventory_account_id' => $this->inventoryAccount->id,
        'wip_account_id' => $this->wipAccount->id,
        'cogs_account_id' => $this->cogsAccount->id,
        'production_overhead_account_id' => $this->overheadAccount->id,
    ]);

    // BOM: 1 sheet → 1 panel, with a 30-min operation at £60/hour = £30 labour/unit
    $this->bom = Bom::create([
        'company_id' => $this->company->id, 'product_variant_id' => $this->fgVariant->id,
        'name' => 'Panel BOM', 'version' => '1', 'output_qty' => 1, 'is_active' => true, 'is_default' => true,
    ]);
    BomItem::create([
        'bom_id' => $this->bom->id, 'product_variant_id' => $this->rawVariant->id,
        'quantity' => 1, 'item_type' => 'material', 'wastage_pct' => 0,
    ]);
    BomOperation::create([
        'bom_id' => $this->bom->id, 'company_id' => $this->company->id,
        'sequence' => 1, 'name' => 'Press', 'work_center' => 'Press', 'duration_minutes' => 30, 'cost_per_hour' => 60,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

function opCostComplete(object $test, float $qty): ProductionOrder
{
    $adj = StockAdjustment::create([
        'company_id' => $test->company->id, 'reference_no' => 'SEED-'.uniqid(),
        'date' => now()->toDateString(), 'warehouse_id' => $test->warehouse->id,
        'create_user_id' => $test->user->id, 'status' => 'approved',
    ]);
    DB::transaction(fn () => app(InventoryLayerReceiptService::class)->receive(
        $test->company, $adj, $test->rawVariant->id, $test->warehouse->id,
        20, 20.0, ChangeTypeEnum::PURCHASE, $test->user->id, null,
    ));

    $res = $test->postJson('/api/admin/production-order', [
        'bom_id' => $test->bom->id, 'warehouse_id' => $test->warehouse->id, 'planned_qty' => $qty,
    ]);
    $orderId = $res->json('data.id');
    $consumptionId = collect($res->json('data.consumptions'))
        ->firstWhere('product_variant_id', $test->rawVariant->id)['id'];
    $test->postJson("/api/admin/production-order/{$orderId}/start")->assertOk();
    $test->postJson("/api/admin/production-order/{$orderId}/complete", [
        'produced_qty' => $qty,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => $qty]],
    ])->assertOk();

    return ProductionOrder::findOrFail($orderId);
}

it('absorbs routing labour (duration × rate) into the finished goods cost', function () {
    $order = opCostComplete($this, 1);

    // material £20 + operation 30min @ £60/h = £30 → £50/unit
    $fg = StockMovement::withoutGlobalScopes()
        ->where('reference_type', ProductionOrder::class)->where('reference_id', $order->id)
        ->where('type', ChangeTypeEnum::FINISHED_GOODS)->first();
    expect((float) $fg->unit_cost)->toBe(50.0);
});

it('posts the operation labour to WIP via the conversion absorption journal', function () {
    $order = opCostComplete($this, 1);

    $overheadCr = JournalItem::query()->where('account_id', $this->overheadAccount->id)->sum('cr_amount');
    expect(round((float) $overheadCr, 2))->toBe(30.0);

    $wipDr = JournalItem::query()->where('account_id', $this->wipAccount->id)->sum('dr_amount');
    $wipCr = JournalItem::query()->where('account_id', $this->wipAccount->id)->sum('cr_amount');
    // WIP Dr: material 20 + labour 30 = 50; WIP Cr: finished goods 50
    expect(round((float) $wipDr, 2))->toBe(50.0);
    expect(round((float) $wipCr, 2))->toBe(50.0);
});

it('scales operation labour with the produced quantity', function () {
    $order = opCostComplete($this, 3);

    // 3 panels: material 3×20=60, labour 3×30=90 → 150 / 3 = £50/unit
    $fg = StockMovement::withoutGlobalScopes()
        ->where('reference_type', ProductionOrder::class)->where('reference_id', $order->id)
        ->where('type', ChangeTypeEnum::FINISHED_GOODS)->first();
    expect((float) $fg->unit_cost)->toBe(50.0);
    expect((float) $fg->total_cost)->toBe(150.0);
});

it('persists cost_per_hour through the BOM operation API', function () {
    $response = $this->postJson("/api/admin/bom/{$this->bom->id}/operations", [
        'sequence' => 2, 'name' => 'Paint', 'work_center' => 'Booth',
        'duration_minutes' => 15, 'cost_per_hour' => 40,
    ]);

    $response->assertCreated();
    $op = BomOperation::findOrFail($response->json('data.id'));
    expect((float) $op->cost_per_hour)->toBe(40.0);
});
