<?php

use App\Models\Bom;
use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\BomItem;
use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\JournalItem;
use App\Enums\PartyTypeEnum;
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

function subWarmCache(): void
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
    subWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);
    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Subcontract Co', 'code' => 'SUB',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);
    $this->user = User::create([
        'company_id' => $this->company->id, 'name' => 'Sub Tester',
        'email' => 'sub-'.uniqid().'@example.com',
        'password' => bcrypt('password'), 'user_type' => UserTypeEnum::ADMIN,
    ]);
    $this->warehouse = Warehouse::create(['company_id' => $this->company->id, 'name' => 'Factory', 'code' => 'FACT']);
    $this->vendor = Party::create([
        'company_id' => $this->company->id, 'name' => 'Plating Vendor',
        'code' => 'PV-1', 'type' => PartyTypeEnum::SUPPLIER,
    ]);

    $rawProduct = Product::create(['company_id' => $this->company->id, 'name' => 'Blank', 'code' => 'BLANK']);
    $this->rawVariant = ProductVariant::create([
        'company_id' => $this->company->id, 'product_id' => $rawProduct->id,
        'sku' => 'BLANK-1', 'is_default' => true, 'purchase_price' => 20.0,
    ]);
    $fgProduct = Product::create(['company_id' => $this->company->id, 'name' => 'Plated Part', 'code' => 'PLATED']);
    $this->fgVariant = ProductVariant::create([
        'company_id' => $this->company->id, 'product_id' => $fgProduct->id,
        'sku' => 'PLATED-1', 'is_default' => true,
    ]);

    $this->inventoryAccount = Account::create(['company_id' => $this->company->id, 'account_group_id' => null, 'name' => 'Inventory', 'code' => 'INV-SC']);
    $this->wipAccount = Account::create(['company_id' => $this->company->id, 'account_group_id' => null, 'name' => 'WIP', 'code' => 'WIP-SC']);
    $this->cogsAccount = Account::create(['company_id' => $this->company->id, 'account_group_id' => null, 'name' => 'COGS', 'code' => 'COGS-SC']);
    $this->subcontractAccount = Account::create(['company_id' => $this->company->id, 'account_group_id' => null, 'name' => 'Subcontract Accrued', 'code' => 'SCA-SC']);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'inventory_account_id' => $this->inventoryAccount->id,
        'wip_account_id' => $this->wipAccount->id,
        'cogs_account_id' => $this->cogsAccount->id,
        'subcontract_account_id' => $this->subcontractAccount->id,
    ]);

    // Subcontracted BOM: 1 blank → 1 plated part, vendor charges £15/unit
    $this->bom = Bom::create([
        'company_id' => $this->company->id, 'product_variant_id' => $this->fgVariant->id,
        'name' => 'Plating BOM', 'version' => '1', 'output_qty' => 1,
        'is_active' => true, 'is_default' => true,
        'is_subcontracted' => true, 'subcontract_charge' => 15, 'subcontractor_party_id' => $this->vendor->id,
    ]);
    BomItem::create([
        'bom_id' => $this->bom->id, 'product_variant_id' => $this->rawVariant->id,
        'quantity' => 1, 'item_type' => 'material', 'wastage_pct' => 0,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

function subSeedRawStock(object $test, float $qty, float $unitCost): void
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

function subCompleteOne(object $test): ProductionOrder
{
    subSeedRawStock($test, 10, 20.0);
    $res = $test->postJson('/api/admin/production-order', [
        'bom_id' => $test->bom->id, 'warehouse_id' => $test->warehouse->id, 'planned_qty' => 1,
    ]);
    $orderId = $res->json('data.id');
    $consumptionId = $res->json('data.consumptions.0.id');
    $test->postJson("/api/admin/production-order/{$orderId}/start")->assertOk();
    $test->postJson("/api/admin/production-order/{$orderId}/complete", [
        'produced_qty' => 1,
        'consumptions' => [['id' => $consumptionId, 'consumed_qty' => 1]],
    ])->assertOk();

    return ProductionOrder::findOrFail($orderId);
}

it('includes the subcontract charge in the finished goods cost', function () {
    $order = subCompleteOne($this);

    // material £20 + subcontract £15 = £35 for 1 unit
    $fg = StockMovement::withoutGlobalScopes()
        ->where('reference_type', ProductionOrder::class)->where('reference_id', $order->id)
        ->where('type', ChangeTypeEnum::FINISHED_GOODS)->first();
    expect((float) $fg->unit_cost)->toBe(35.0);
});

it('posts WIP Dr / Subcontract Accrued Cr for the charge', function () {
    $order = subCompleteOne($this);

    $subDr = JournalItem::query()->where('account_id', $this->wipAccount->id)
        ->whereIn('journal_id', function ($q) use ($order) {
            $q->select('id')->from('journals')
                ->where('reference_type', (new ProductionOrder)->getMorphClass())
                ->where('reference_id', $order->id)
                ->where('remarks', 'like', '%subcontract%');
        })->sum('dr_amount');
    expect(round((float) $subDr, 2))->toBe(15.0);

    $accrued = JournalItem::query()->where('account_id', $this->subcontractAccount->id)->sum('cr_amount');
    expect(round((float) $accrued, 2))->toBe(15.0);
});

it('nets WIP to zero on a subcontracted order', function () {
    subCompleteOne($this);

    $wipDr = JournalItem::query()->where('account_id', $this->wipAccount->id)->sum('dr_amount');
    $wipCr = JournalItem::query()->where('account_id', $this->wipAccount->id)->sum('cr_amount');

    // WIP Dr: material 20 + subcontract 15 = 35; WIP Cr: finished goods 35
    expect(round((float) $wipDr, 2))->toBe(35.0);
    expect(round((float) $wipCr, 2))->toBe(35.0);
});

it('persists subcontracting fields through the BOM API', function () {
    $response = $this->postJson('/api/admin/bom', [
        'product_variant_id' => $this->fgVariant->id,
        'name' => 'API Sub BOM', 'output_qty' => 1,
        'is_subcontracted' => true, 'subcontract_charge' => 12.5,
        'subcontractor_party_id' => $this->vendor->id,
        'items' => [
            ['product_variant_id' => $this->rawVariant->id, 'quantity' => 1, 'item_type' => 'material'],
        ],
    ]);

    $response->assertCreated();
    $bom = Bom::findOrFail($response->json('data.id'));
    expect($bom->is_subcontracted)->toBeTrue();
    expect((float) $bom->subcontract_charge)->toBe(12.5);
    expect($bom->subcontractor_party_id)->toBe($this->vendor->id);
});

it('does not absorb subcontract charge when the BOM is not subcontracted', function () {
    $this->bom->update(['is_subcontracted' => false]);

    $order = subCompleteOne($this);

    $fg = StockMovement::withoutGlobalScopes()
        ->where('reference_type', ProductionOrder::class)->where('reference_id', $order->id)
        ->where('type', ChangeTypeEnum::FINISHED_GOODS)->first();
    expect((float) $fg->unit_cost)->toBe(20.0); // material only
});
