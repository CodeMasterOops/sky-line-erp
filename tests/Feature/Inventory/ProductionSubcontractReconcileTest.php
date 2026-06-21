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

function subRecWarmCache(): void
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
    subRecWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);
    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'SubRec Co', 'code' => 'SR',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);
    $this->user = User::create([
        'company_id' => $this->company->id, 'name' => 'SubRec Tester',
        'email' => 'subrec-'.uniqid().'@example.com',
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
    $fgProduct = Product::create(['company_id' => $this->company->id, 'name' => 'Plated', 'code' => 'PLATED']);
    $this->fgVariant = ProductVariant::create([
        'company_id' => $this->company->id, 'product_id' => $fgProduct->id,
        'sku' => 'PLATED-1', 'is_default' => true,
    ]);

    $this->inventoryAccount = Account::create(['company_id' => $this->company->id, 'account_group_id' => null, 'name' => 'Inventory', 'code' => 'INV-R']);
    $this->wipAccount = Account::create(['company_id' => $this->company->id, 'account_group_id' => null, 'name' => 'WIP', 'code' => 'WIP-R']);
    $this->cogsAccount = Account::create(['company_id' => $this->company->id, 'account_group_id' => null, 'name' => 'COGS', 'code' => 'COGS-R']);
    $this->subAccount = Account::create(['company_id' => $this->company->id, 'account_group_id' => null, 'name' => 'Subcontract Accrued', 'code' => 'SCA-R']);
    $this->apAccount = Account::create(['company_id' => $this->company->id, 'account_group_id' => null, 'name' => 'Suppliers', 'code' => 'AP-R']);
    $this->varianceAccount = Account::create(['company_id' => $this->company->id, 'account_group_id' => null, 'name' => 'Mfg Variance', 'code' => 'VAR-R']);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'inventory_account_id' => $this->inventoryAccount->id,
        'wip_account_id' => $this->wipAccount->id,
        'cogs_account_id' => $this->cogsAccount->id,
        'subcontract_account_id' => $this->subAccount->id,
        'supplier_account_id' => $this->apAccount->id,
        'manufacturing_variance_account_id' => $this->varianceAccount->id,
    ]);

    // Subcontracted BOM: 1 blank → 1 plated, vendor standard £15/unit
    $this->bom = Bom::create([
        'company_id' => $this->company->id, 'product_variant_id' => $this->fgVariant->id,
        'name' => 'Plating BOM', 'version' => '1', 'output_qty' => 1, 'is_active' => true, 'is_default' => true,
        'is_subcontracted' => true, 'subcontract_charge' => 15, 'subcontractor_party_id' => $this->vendor->id,
    ]);
    BomItem::create([
        'bom_id' => $this->bom->id, 'product_variant_id' => $this->rawVariant->id,
        'quantity' => 1, 'item_type' => 'material', 'wastage_pct' => 0,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);

    $adj = StockAdjustment::create([
        'company_id' => $this->company->id, 'reference_no' => 'SEED-'.uniqid(),
        'date' => now()->toDateString(), 'warehouse_id' => $this->warehouse->id,
        'create_user_id' => $this->user->id, 'status' => 'approved',
    ]);
    DB::transaction(fn () => app(InventoryLayerReceiptService::class)->receive(
        $this->company, $adj, $this->rawVariant->id, $this->warehouse->id,
        10, 20.0, ChangeTypeEnum::PURCHASE, $this->user->id, null,
    ));
});

function subRecComplete(object $test): ProductionOrder
{
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

it('clears the accrual against AP and books the variance when actual exceeds standard', function () {
    $order = subRecComplete($this); // absorbed £15

    $this->postJson("/api/admin/production-order/{$order->id}/reconcile-subcontract", [
        'actual_amount' => 20,
    ])->assertOk();

    // Reconciliation: Accrual Dr 15, Variance Dr 5, AP Cr 20
    $reconJournalId = DB::table('journals')
        ->where('reference_id', $order->id)
        ->where('voucher_no', 'like', 'INV-JV-POSR%')->value('id');

    $items = JournalItem::query()->where('journal_id', $reconJournalId)->get();
    expect((float) $items->where('account_id', $this->subAccount->id)->sum('dr_amount'))->toBe(15.0);
    expect((float) $items->where('account_id', $this->varianceAccount->id)->sum('dr_amount'))->toBe(5.0);
    expect((float) $items->where('account_id', $this->apAccount->id)->sum('cr_amount'))->toBe(20.0);
});

it('nets the subcontract accrual account to zero after reconciliation', function () {
    $order = subRecComplete($this);
    $this->postJson("/api/admin/production-order/{$order->id}/reconcile-subcontract", ['actual_amount' => 20])->assertOk();

    $accrualCr = JournalItem::query()->where('account_id', $this->subAccount->id)->sum('cr_amount');
    $accrualDr = JournalItem::query()->where('account_id', $this->subAccount->id)->sum('dr_amount');
    expect(round((float) $accrualCr, 2))->toBe(15.0); // absorbed
    expect(round((float) $accrualDr, 2))->toBe(15.0); // cleared
});

it('credits the variance account when actual is below standard', function () {
    $order = subRecComplete($this);
    $this->postJson("/api/admin/production-order/{$order->id}/reconcile-subcontract", ['actual_amount' => 12])->assertOk();

    $reconJournalId = DB::table('journals')
        ->where('reference_id', $order->id)
        ->where('voucher_no', 'like', 'INV-JV-POSR%')->value('id');
    $items = JournalItem::query()->where('journal_id', $reconJournalId)->get();

    expect((float) $items->where('account_id', $this->apAccount->id)->sum('cr_amount'))->toBe(12.0);
    expect((float) $items->where('account_id', $this->varianceAccount->id)->sum('cr_amount'))->toBe(3.0);
});

it('rejects reconciling the same order twice', function () {
    $order = subRecComplete($this);
    $this->postJson("/api/admin/production-order/{$order->id}/reconcile-subcontract", ['actual_amount' => 15])->assertOk();
    $this->postJson("/api/admin/production-order/{$order->id}/reconcile-subcontract", ['actual_amount' => 15])->assertStatus(422);
});

it('rejects reconciling an order with no absorbed subcontract charge', function () {
    $this->bom->update(['is_subcontracted' => false]);
    $order = subRecComplete($this); // nothing absorbed

    $this->postJson("/api/admin/production-order/{$order->id}/reconcile-subcontract", ['actual_amount' => 15])
        ->assertStatus(422);
});
