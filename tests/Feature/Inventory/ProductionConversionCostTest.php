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
use Laravel\Sanctum\Sanctum;
use App\Enums\ChangeTypeEnum;
use App\Models\StockMovement;
use App\Enums\ProductTypeEnum;
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

function convWarmCache(): void
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
    convWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Conversion Mfg Co',
        'code' => 'CONV',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Conv Tester',
        'email' => 'conv-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->warehouse = Warehouse::create(['company_id' => $this->company->id, 'name' => 'Factory', 'code' => 'FACT']);

    // Raw material @ £20
    $rawProduct = Product::create(['company_id' => $this->company->id, 'name' => 'Steel', 'code' => 'STEEL']);
    $this->rawVariant = ProductVariant::create([
        'company_id' => $this->company->id, 'product_id' => $rawProduct->id,
        'sku' => 'STEEL-1', 'is_default' => true, 'purchase_price' => 20.0,
    ]);

    // Labour & overhead are service items referenced by BOM lines
    $labourProduct = Product::create([
        'company_id' => $this->company->id, 'product_type' => ProductTypeEnum::SERVICE,
        'name' => 'Machining Labour', 'code' => 'LAB',
    ]);
    $this->labourVariant = ProductVariant::create([
        'company_id' => $this->company->id, 'product_id' => $labourProduct->id,
        'sku' => 'LAB-1', 'is_default' => true,
    ]);

    // Finished good
    $fgProduct = Product::create(['company_id' => $this->company->id, 'name' => 'Bracket', 'code' => 'BRK']);
    $this->fgVariant = ProductVariant::create([
        'company_id' => $this->company->id, 'product_id' => $fgProduct->id,
        'sku' => 'BRK-1', 'is_default' => true,
    ]);

    $this->inventoryAccount = Account::create(['company_id' => $this->company->id, 'account_group_id' => null, 'name' => 'Inventory', 'code' => 'INV-C']);
    $this->wipAccount = Account::create(['company_id' => $this->company->id, 'account_group_id' => null, 'name' => 'WIP', 'code' => 'WIP-C']);
    $this->cogsAccount = Account::create(['company_id' => $this->company->id, 'account_group_id' => null, 'name' => 'COGS', 'code' => 'COGS-C']);
    $this->overheadAccount = Account::create(['company_id' => $this->company->id, 'account_group_id' => null, 'name' => 'Production Overhead Applied', 'code' => 'POH-C']);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'inventory_account_id' => $this->inventoryAccount->id,
        'wip_account_id' => $this->wipAccount->id,
        'cogs_account_id' => $this->cogsAccount->id,
        'production_overhead_account_id' => $this->overheadAccount->id,
    ]);

    // BOM: 2 steel + 1 labour @ £30 + 1 overhead @ £10 → 1 bracket
    $this->bom = Bom::create([
        'company_id' => $this->company->id, 'product_variant_id' => $this->fgVariant->id,
        'name' => 'Bracket BOM', 'version' => '1', 'output_qty' => 1, 'is_active' => true, 'is_default' => true,
    ]);
    BomItem::create([
        'bom_id' => $this->bom->id, 'product_variant_id' => $this->rawVariant->id,
        'quantity' => 2, 'item_type' => 'material', 'wastage_pct' => 0,
    ]);
    BomItem::create([
        'bom_id' => $this->bom->id, 'product_variant_id' => $this->labourVariant->id,
        'quantity' => 1, 'item_type' => 'labour', 'wastage_pct' => 0, 'standard_rate' => 30,
    ]);
    BomItem::create([
        'bom_id' => $this->bom->id, 'product_variant_id' => $this->labourVariant->id,
        'quantity' => 1, 'item_type' => 'overhead', 'wastage_pct' => 0, 'standard_rate' => 10,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

function convSeedRawStock(object $test, float $qty, float $unitCost): void
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

function convCompleteOneBracket(object $test): ProductionOrder
{
    convSeedRawStock($test, 10, 20.0);
    $res = $test->postJson('/api/admin/production-order', [
        'bom_id' => $test->bom->id, 'warehouse_id' => $test->warehouse->id, 'planned_qty' => 1,
    ]);
    $orderId = $res->json('data.id');
    $materialConsumptionId = collect($res->json('data.consumptions'))
        ->firstWhere('product_variant_id', $test->rawVariant->id)['id'];
    $test->postJson("/api/admin/production-order/{$orderId}/start")->assertOk();

    $test->postJson("/api/admin/production-order/{$orderId}/complete", [
        'produced_qty' => 1,
        'consumptions' => [['id' => $materialConsumptionId, 'consumed_qty' => 2]],
    ])->assertOk();

    return ProductionOrder::findOrFail($orderId);
}

it('persists standard_rate on labour BOM lines through the API', function () {
    $response = $this->postJson('/api/admin/bom', [
        'product_variant_id' => $this->fgVariant->id,
        'name' => 'API BOM',
        'output_qty' => 1,
        'items' => [
            ['product_variant_id' => $this->rawVariant->id, 'quantity' => 2, 'item_type' => 'material'],
            ['product_variant_id' => $this->labourVariant->id, 'quantity' => 1, 'item_type' => 'labour', 'standard_rate' => 45],
        ],
    ]);

    $response->assertCreated();
    $bomId = $response->json('data.id');
    $labourLine = \App\Models\BomItem::query()
        ->where('bom_id', $bomId)->where('item_type', 'labour')->first();

    expect((float) $labourLine->standard_rate)->toBe(45.0);
});

it('includes standard labour and overhead in the finished goods unit cost', function () {
    $order = convCompleteOneBracket($this);

    $fgMovement = StockMovement::withoutGlobalScopes()
        ->where('reference_type', ProductionOrder::class)
        ->where('reference_id', $order->id)
        ->where('type', ChangeTypeEnum::FINISHED_GOODS)
        ->first();

    // material 2 × £20 = £40, labour £30, overhead £10 → £80 for 1 unit
    expect((float) $fgMovement->unit_cost)->toBe(80.0);
    expect((float) $fgMovement->total_cost)->toBe(80.0);
});

it('posts a WIP Dr / Production Overhead Applied Cr absorption journal', function () {
    $order = convCompleteOneBracket($this);

    $order->refresh();
    expect($order->gl_journal_id)->not->toBeNull();

    $items = JournalItem::query()->where('journal_id', $order->gl_journal_id)->get();
    $debit = $items->firstWhere('dr_amount', '>', 0);
    $credit = $items->firstWhere('cr_amount', '>', 0);

    expect($debit->account_id)->toBe($this->wipAccount->id);
    expect((float) $debit->dr_amount)->toBe(40.0); // labour 30 + overhead 10
    expect($credit->account_id)->toBe($this->overheadAccount->id);
    expect((float) $credit->cr_amount)->toBe(40.0);
});

it('nets the WIP account to zero across the completed order', function () {
    $order = convCompleteOneBracket($this);

    $wipDr = JournalItem::query()->where('account_id', $this->wipAccount->id)->sum('dr_amount');
    $wipCr = JournalItem::query()->where('account_id', $this->wipAccount->id)->sum('cr_amount');

    // WIP Dr: material 40 + conversion 40 = 80; WIP Cr: finished goods 80
    expect(round((float) $wipDr, 2))->toBe(80.0);
    expect(round((float) $wipCr, 2))->toBe(80.0);
});

it('falls back to material-only valuation when no production overhead account is configured', function () {
    AccountSetting::withoutGlobalScopes()->where('company_id', $this->company->id)
        ->update(['production_overhead_account_id' => null]);

    $order = convCompleteOneBracket($this);

    $fgMovement = StockMovement::withoutGlobalScopes()
        ->where('reference_type', ProductionOrder::class)
        ->where('reference_id', $order->id)
        ->where('type', ChangeTypeEnum::FINISHED_GOODS)
        ->first();

    // conversion not absorbed → material 40 only; WIP still balances
    expect((float) $fgMovement->unit_cost)->toBe(40.0);

    $wipDr = JournalItem::query()->where('account_id', $this->wipAccount->id)->sum('dr_amount');
    $wipCr = JournalItem::query()->where('account_id', $this->wipAccount->id)->sum('cr_amount');
    expect(round((float) $wipDr, 2))->toBe(40.0);
    expect(round((float) $wipCr, 2))->toBe(40.0);
});
