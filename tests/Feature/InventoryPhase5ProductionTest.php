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
use App\Models\BomOperation;
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

function p5pWarmCache(): void
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
    p5pWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026-P5P', 'year_code' => '26P5P',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Phase5 Production Co',
        'code' => 'P5PC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'P5 Prod Tester',
        'email' => 'p5p-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'P5P Factory', 'code' => 'P5PF',
    ]);

    $rawProduct = Product::create(['company_id' => $this->company->id, 'name' => 'P5P Raw', 'code' => 'P5PRAW']);
    $this->rawVariant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $rawProduct->id,
        'sku' => 'P5PRAW-1', 'is_default' => true,
        'purchase_price' => 10.0,
    ]);

    $fgProduct = Product::create(['company_id' => $this->company->id, 'name' => 'P5P FG', 'code' => 'P5PFG']);
    $this->fgVariant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $fgProduct->id,
        'sku' => 'P5PFG-1', 'is_default' => true,
    ]);

    $invAccount = Account::create(['company_id' => $this->company->id, 'account_group_id' => null, 'name' => 'P5P Inv', 'code' => 'P5P-INV']);
    $wipAccount = Account::create(['company_id' => $this->company->id, 'account_group_id' => null, 'name' => 'P5P WIP', 'code' => 'P5P-WIP']);
    $cogsAccount = Account::create(['company_id' => $this->company->id, 'account_group_id' => null, 'name' => 'P5P COGS', 'code' => 'P5P-COGS']);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'inventory_account_id' => $invAccount->id,
        'wip_account_id' => $wipAccount->id,
        'cogs_account_id' => $cogsAccount->id,
    ]);

    $this->bom = Bom::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->fgVariant->id,
        'name' => 'P5P BOM v1',
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

function p5pSeedStock(object $test, int $qty = 10): void
{
    $adj = StockAdjustment::create([
        'company_id' => $test->company->id,
        'reference_no' => 'P5SEED-'.uniqid(),
        'date' => now()->toDateString(),
        'warehouse_id' => $test->warehouse->id,
        'create_user_id' => $test->user->id,
        'status' => 'approved',
    ]);
    DB::transaction(fn () => app(InventoryLayerReceiptService::class)->receive(
        $test->company, $adj, $test->rawVariant->id,
        $test->warehouse->id, $qty, 10.0,
        ChangeTypeEnum::PURCHASE, $test->user->id, null,
    ));
}

function p5pCreateOrder(object $test, float $qty = 1): ProductionOrder
{
    $resp = $test->postJson('/api/admin/production-order', [
        'bom_id' => $test->bom->id,
        'warehouse_id' => $test->warehouse->id,
        'planned_qty' => $qty,
    ]);
    $resp->assertCreated();

    return ProductionOrder::findOrFail($resp->json('data.id'));
}

it('can add operations to a BOM', function () {
    $this->postJson("/api/admin/bom/{$this->bom->id}/operations", [
        'sequence' => 1,
        'name' => 'Cutting',
        'work_center' => 'CNC-01',
        'duration_minutes' => 30,
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Cutting')
        ->assertJsonPath('data.sequence', 1);
});

it('can list BOM operations', function () {
    BomOperation::create([
        'company_id' => $this->company->id,
        'bom_id' => $this->bom->id,
        'sequence' => 1,
        'name' => 'Cutting',
    ]);
    BomOperation::create([
        'company_id' => $this->company->id,
        'bom_id' => $this->bom->id,
        'sequence' => 2,
        'name' => 'Welding',
    ]);

    $this->getJson("/api/admin/bom/{$this->bom->id}/operations")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('copies BOM operations to production order when creating an order', function () {
    p5pSeedStock($this, 10);

    BomOperation::create([
        'company_id' => $this->company->id,
        'bom_id' => $this->bom->id,
        'sequence' => 1,
        'name' => 'Assembly',
    ]);
    BomOperation::create([
        'company_id' => $this->company->id,
        'bom_id' => $this->bom->id,
        'sequence' => 2,
        'name' => 'QC',
    ]);

    $order = p5pCreateOrder($this);
    expect($order->operations()->count())->toBe(2);

    $op = $order->operations()->first();
    expect($op->name)->toBe('Assembly')
        ->and($op->status)->toBe('pending');
});

it('creates production order without operations when BOM has none', function () {
    p5pSeedStock($this, 10);
    $order = p5pCreateOrder($this);
    expect($order->operations()->count())->toBe(0);
});

it('can start a production order operation', function () {
    p5pSeedStock($this, 10);

    BomOperation::create([
        'company_id' => $this->company->id,
        'bom_id' => $this->bom->id,
        'sequence' => 1,
        'name' => 'Assembly',
    ]);

    $order = p5pCreateOrder($this);
    $this->postJson("/api/admin/production-order/{$order->id}/start")->assertOk();

    $op = $order->operations()->first();
    $this->postJson("/api/admin/production-order/{$order->id}/operations/{$op->id}/start")
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress');
});

it('can complete a production order operation', function () {
    p5pSeedStock($this, 10);

    BomOperation::create([
        'company_id' => $this->company->id,
        'bom_id' => $this->bom->id,
        'sequence' => 1,
        'name' => 'Assembly',
    ]);

    $order = p5pCreateOrder($this);
    $this->postJson("/api/admin/production-order/{$order->id}/start")->assertOk();
    $op = $order->operations()->first();
    $this->postJson("/api/admin/production-order/{$order->id}/operations/{$op->id}/start")->assertOk();

    $this->postJson("/api/admin/production-order/{$order->id}/operations/{$op->id}/complete", ['remarks' => 'Done'])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    expect($op->fresh()->completed_by)->toBe($this->user->id);
});

it('can skip a production order operation', function () {
    p5pSeedStock($this, 10);

    BomOperation::create([
        'company_id' => $this->company->id,
        'bom_id' => $this->bom->id,
        'sequence' => 1,
        'name' => 'Optional Step',
    ]);

    $order = p5pCreateOrder($this);
    $this->postJson("/api/admin/production-order/{$order->id}/start")->assertOk();
    $op = $order->operations()->first();

    $this->postJson("/api/admin/production-order/{$order->id}/operations/{$op->id}/skip")
        ->assertOk()
        ->assertJsonPath('data.status', 'skipped');
});

it('rejects starting an operation on a draft order', function () {
    p5pSeedStock($this, 10);

    BomOperation::create([
        'company_id' => $this->company->id,
        'bom_id' => $this->bom->id,
        'sequence' => 1,
        'name' => 'Assembly',
    ]);

    $order = p5pCreateOrder($this);
    $op = $order->operations()->first();

    $this->postJson("/api/admin/production-order/{$order->id}/operations/{$op->id}/start")
        ->assertUnprocessable();
});

it('rejects completing an operation that is still pending', function () {
    p5pSeedStock($this, 10);

    BomOperation::create([
        'company_id' => $this->company->id,
        'bom_id' => $this->bom->id,
        'sequence' => 1,
        'name' => 'Assembly',
    ]);

    $order = p5pCreateOrder($this);
    $this->postJson("/api/admin/production-order/{$order->id}/start")->assertOk();
    $op = $order->operations()->first();

    $this->postJson("/api/admin/production-order/{$order->id}/operations/{$op->id}/complete")
        ->assertUnprocessable();
});

it('shows operations on the production order show endpoint', function () {
    p5pSeedStock($this, 10);

    BomOperation::create([
        'company_id' => $this->company->id,
        'bom_id' => $this->bom->id,
        'sequence' => 1,
        'name' => 'Assembly',
    ]);

    $order = p5pCreateOrder($this);

    $this->getJson("/api/admin/production-order/{$order->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.operations');
});

it('includes operations in the bom show endpoint', function () {
    BomOperation::create([
        'company_id' => $this->company->id,
        'bom_id' => $this->bom->id,
        'sequence' => 1,
        'name' => 'Assembly',
    ]);

    $this->getJson("/api/admin/bom/{$this->bom->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.operations');
});
