<?php

use App\Models\User;
use App\Models\Stock;
use App\Models\Account;
use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\JournalItem;
use App\Models\DamageReport;
use Laravel\Sanctum\Sanctum;
use App\Enums\ChangeTypeEnum;
use App\Models\StockMovement;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use App\Models\InventoryValuationSnapshot;
use App\Services\Inventory\InventoryLayerReceiptService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function p4WarmCache(): void
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
    p4WarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026-P4', 'year_code' => '26P4',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Phase4 Test Co',
        'code' => 'P4TC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Phase4 Tester',
        'email' => 'p4-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'P4 Warehouse', 'code' => 'P4WH',
    ]);

    $product = Product::create(['company_id' => $this->company->id, 'name' => 'P4 Widget', 'code' => 'P4WGT']);
    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'sku' => 'P4WGT-1', 'is_default' => true,
        'purchase_price' => 50.0,
    ]);

    $this->inventoryAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'P4 Inventory', 'code' => 'P4-INV',
    ]);
    $this->cogsAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'P4 COGS', 'code' => 'P4-COGS',
    ]);
    $this->stockAdjAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'P4 Stock Adj', 'code' => 'P4-SADJ',
    ]);
    $this->damageAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'P4 Damage Expense', 'code' => 'P4-DMG',
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'inventory_account_id' => $this->inventoryAccount->id,
        'cogs_account_id' => $this->cogsAccount->id,
        'stock_adjustment_account_id' => $this->stockAdjAccount->id,
        'damage_account_id' => $this->damageAccount->id,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

function p4SeedStock(object $test, int $qty, float $unitCost): void
{
    $adj = StockAdjustment::create([
        'company_id' => $test->company->id,
        'reference_no' => 'P4SEED-'.uniqid(),
        'date' => now()->toDateString(),
        'warehouse_id' => $test->warehouse->id,
        'create_user_id' => $test->user->id,
        'status' => 'approved',
    ]);

    $receipt = app(InventoryLayerReceiptService::class);
    DB::transaction(fn () => $receipt->receive(
        $test->company, $adj, $test->variant->id,
        $test->warehouse->id, $qty, $unitCost,
        ChangeTypeEnum::PURCHASE, $test->user->id, null,
    ));
}

// ============================================================
// Damage Report CRUD Tests
// ============================================================

it('can create a draft damage report', function () {
    $response = $this->postJson('/api/admin/damage-report', [
        'date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'reason' => 'flood damage',
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'quantity' => 3,
        ]],
    ]);

    $response->assertCreated();
    expect($response->json('data.status'))->toBe('draft');
    expect($response->json('data.reason'))->toBe('flood damage');
});

it('can update a draft damage report', function () {
    $r = $this->postJson('/api/admin/damage-report', [
        'date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 2]],
    ])->assertCreated();

    $id = $r->json('data.id');

    $this->putJson("/api/admin/damage-report/{$id}", [
        'date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'reason' => 'updated reason',
        'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 5]],
    ])->assertSuccessful();

    expect(DamageReport::find($id)->reason)->toBe('updated reason');
});

it('can delete a draft damage report', function () {
    $r = $this->postJson('/api/admin/damage-report', [
        'date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 2]],
    ])->assertCreated();

    $id = $r->json('data.id');

    $this->deleteJson("/api/admin/damage-report/{$id}")->assertSuccessful();

    expect(DamageReport::find($id))->toBeNull();
});

it('cannot edit an approved damage report', function () {
    p4SeedStock($this, 10, 50.0);

    $r = $this->postJson('/api/admin/damage-report', [
        'date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 2]],
    ])->assertCreated();

    $id = $r->json('data.id');
    $this->postJson("/api/admin/damage-report/{$id}/approve")->assertSuccessful();

    $this->putJson("/api/admin/damage-report/{$id}", [
        'date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 1]],
    ])->assertUnprocessable();
});

// ============================================================
// Damage Report Approval — Stock & GL Effects
// ============================================================

it('approving a damage report deducts stock and creates a DAMAGE movement', function () {
    p4SeedStock($this, 10, 50.0);

    $r = $this->postJson('/api/admin/damage-report', [
        'date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'reason' => 'water damage',
        'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 3]],
    ])->assertCreated();

    $id = $r->json('data.id');
    $this->postJson("/api/admin/damage-report/{$id}/approve")->assertSuccessful();

    // Stock deducted
    $stock = Stock::where('company_id', $this->company->id)
        ->where('product_variant_id', $this->variant->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();
    expect((int) $stock->quantity)->toBe(7);

    // DAMAGE movement exists
    $movement = StockMovement::where('reference_id', $id)
        ->where('reference_type', DamageReport::class)
        ->where('type', ChangeTypeEnum::DAMAGE)
        ->first();
    expect($movement)->not->toBeNull();
    expect((int) $movement->quantity)->toBe(3);
    expect((float) $movement->unit_cost)->toBe(50.0);
});

it('approving a damage report posts Dr Damage Expense / Cr Inventory GL journal', function () {
    p4SeedStock($this, 10, 50.0);

    $r = $this->postJson('/api/admin/damage-report', [
        'date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 2]],
    ])->assertCreated();

    $id = $r->json('data.id');
    $this->postJson("/api/admin/damage-report/{$id}/approve")->assertSuccessful();

    $movement = StockMovement::where('reference_id', $id)
        ->where('reference_type', DamageReport::class)
        ->where('type', ChangeTypeEnum::DAMAGE)
        ->first();

    expect($movement->gl_journal_id)->not->toBeNull();

    // Dr Damage account
    $drItem = JournalItem::where('journal_id', $movement->gl_journal_id)
        ->where('account_id', $this->damageAccount->id)
        ->where('dr_amount', '>', 0)
        ->first();
    expect($drItem)->not->toBeNull();
    expect((float) $drItem->dr_amount)->toBe(100.0); // 2 × 50

    // Cr Inventory account
    $crItem = JournalItem::where('journal_id', $movement->gl_journal_id)
        ->where('account_id', $this->inventoryAccount->id)
        ->where('cr_amount', '>', 0)
        ->first();
    expect($crItem)->not->toBeNull();
    expect((float) $crItem->cr_amount)->toBe(100.0);
});

it('damage GL falls back to stock_adjustment_account when damage_account_id is not set', function () {
    // Replace AccountSetting without damage_account_id
    AccountSetting::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->forceDelete();

    AccountSetting::create([
        'company_id' => $this->company->id,
        'inventory_account_id' => $this->inventoryAccount->id,
        'cogs_account_id' => $this->cogsAccount->id,
        'stock_adjustment_account_id' => $this->stockAdjAccount->id,
        // no damage_account_id
    ]);

    p4WarmCache();
    p4SeedStock($this, 5, 50.0);

    $r = $this->postJson('/api/admin/damage-report', [
        'date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 1]],
    ])->assertCreated();

    $id = $r->json('data.id');
    $this->postJson("/api/admin/damage-report/{$id}/approve")->assertSuccessful();

    $movement = StockMovement::where('reference_id', $id)
        ->where('reference_type', DamageReport::class)
        ->first();

    // Journal should be posted using stock_adjustment_account as fallback
    expect($movement->gl_journal_id)->not->toBeNull();

    $drItem = JournalItem::where('journal_id', $movement->gl_journal_id)
        ->where('account_id', $this->stockAdjAccount->id)
        ->where('dr_amount', '>', 0)
        ->first();
    expect($drItem)->not->toBeNull();
});

it('prevents damaging more stock than available', function () {
    p4SeedStock($this, 3, 50.0);

    $r = $this->postJson('/api/admin/damage-report', [
        'date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 10]],
    ])->assertCreated();

    $id = $r->json('data.id');
    $this->postJson("/api/admin/damage-report/{$id}/approve")->assertUnprocessable();
});

it('approving damage report directly on creation works via status=approved', function () {
    p4SeedStock($this, 5, 50.0);

    $this->postJson('/api/admin/damage-report', [
        'date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'status' => 'approved',
        'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 2]],
    ])->assertCreated()
        ->assertJsonPath('data.status', 'approved');

    $stock = Stock::where('company_id', $this->company->id)
        ->where('product_variant_id', $this->variant->id)
        ->first();
    expect((int) $stock->quantity)->toBe(3);
});

// ============================================================
// Inventory Valuation Snapshot Command Tests
// ============================================================

it('valuation snapshot command captures stock layer totals', function () {
    p4SeedStock($this, 10, 50.0);

    $date = now()->toDateString();

    $this->artisan('inventory:valuation-snapshot', [
        'date' => $date,
        '--company' => $this->company->id,
    ])->assertSuccessful();

    $snapshot = InventoryValuationSnapshot::where('company_id', $this->company->id)
        ->where('snapshot_date', $date)
        ->where('product_variant_id', $this->variant->id)
        ->first();

    expect($snapshot)->not->toBeNull();
    expect((int) $snapshot->qty_remaining)->toBe(10);
    expect((float) $snapshot->total_value)->toBe(500.0);
});

it('valuation snapshot with --replace replaces existing snapshot for same date', function () {
    p4SeedStock($this, 10, 50.0);

    $date = now()->toDateString();

    $this->artisan('inventory:valuation-snapshot', ['date' => $date, '--company' => $this->company->id])
        ->assertSuccessful();

    // Add more stock and re-snapshot with --replace
    p4SeedStock($this, 5, 50.0);

    $this->artisan('inventory:valuation-snapshot', ['date' => $date, '--company' => $this->company->id, '--replace' => true])
        ->assertSuccessful();

    $count = InventoryValuationSnapshot::where('company_id', $this->company->id)
        ->where('snapshot_date', $date)
        ->count();

    expect($count)->toBe(1);

    $snapshot = InventoryValuationSnapshot::where('company_id', $this->company->id)
        ->where('snapshot_date', $date)
        ->first();
    expect((int) $snapshot->qty_remaining)->toBe(15);
});

// ============================================================
// GL Reconcile Command Tests
// ============================================================

it('gl reconcile dry run lists movements without GL journals', function () {
    p4SeedStock($this, 5, 50.0);

    // Manually null out a gl_journal_id to simulate a missing posting
    StockMovement::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->update(['gl_journal_id' => null]);

    $this->artisan('inventory:gl-reconcile', [
        '--company' => $this->company->id,
        '--dry-run' => true,
    ])->assertSuccessful();
});

it('gl reconcile command succeeds when no unposted movements exist', function () {
    p4SeedStock($this, 5, 50.0);

    // All movements should already have journals
    $this->artisan('inventory:gl-reconcile', ['--company' => $this->company->id])
        ->assertSuccessful();
});
