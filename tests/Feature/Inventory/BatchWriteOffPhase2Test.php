<?php

use App\Models\User;
use App\Models\Batch;
use App\Models\Stock;
use App\Models\Branch;
use App\Models\Account;
use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Models\StockLayer;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\StockMovement;
use App\Enums\BatchStatusEnum;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use Illuminate\Validation\ValidationException;
use App\Services\Inventory\InventoryLayerIssueService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Write Off Co',
        'code' => 'WOC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'name' => 'Main Branch',
        'code' => 'BR1',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Write Off Admin',
        'email' => 'wo-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Warehouse A',
        'code' => 'WA',
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Tracked Widget',
        'code' => 'TW',
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'SKU-WO',
        'is_default' => true,
        'is_batch_tracked' => true,
    ]);

    $this->inventoryAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Inventory',
        'code' => 'INV-WO',
    ]);

    $this->damageAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Damage Expense',
        'code' => 'DMG-WO',
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'inventory_account_id' => $this->inventoryAccount->id,
        'damage_account_id' => $this->damageAccount->id,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
    TenantService::setBranchId($this->branch->id);
});

/**
 * Seed an active lot plus a held lot, each backed by a stock layer, and one on-hand
 * stock row covering both. Returns [activeBatch, heldBatch].
 */
function seedWriteOffScenario(object $test, BatchStatusEnum $heldStatus, int $activeQty = 60, int $heldQty = 40): array
{
    $make = function (BatchStatusEnum $status, int $qty) use ($test) {
        $batch = Batch::create([
            'company_id' => $test->company->id,
            'product_variant_id' => $test->variant->id,
            'warehouse_id' => $test->warehouse->id,
            'batch_no' => 'B-'.uniqid(),
            'initial_qty' => $qty,
            'remaining_qty' => $qty,
            'unit_cost' => 10,
            'status' => $status,
        ]);

        StockLayer::create([
            'company_id' => $test->company->id,
            'product_variant_id' => $test->variant->id,
            'warehouse_id' => $test->warehouse->id,
            'qty_remaining' => $qty,
            'unit_cost' => 10,
            'received_at' => now(),
            'batch_id' => $batch->id,
        ]);

        return $batch;
    };

    $active = $make(BatchStatusEnum::Active, $activeQty);
    $held = $make($heldStatus, $heldQty);

    Stock::withoutGlobalScopes()->create([
        'company_id' => $test->company->id,
        'branch_id' => $test->branch->id,
        'product_variant_id' => $test->variant->id,
        'warehouse_id' => $test->warehouse->id,
        'quantity' => $activeQty + $heldQty,
        'on_hold' => 0,
    ]);

    return [$active, $held];
}

it('writes off a recalled batch via the dedicated endpoint and posts the loss to the GL', function () {
    [$active, $recalled] = seedWriteOffScenario($this, BatchStatusEnum::Recalled, activeQty: 60, heldQty: 40);

    $this->postJson("/api/admin/batch/{$recalled->id}/write-off", ['reason' => 'Supplier recall'])
        ->assertOk()
        ->assertJsonPath('data.remaining_qty', 0);

    // The recalled lot is emptied; the active lot is untouched.
    expect((float) $recalled->fresh()->remaining_qty)->toBe(0.0);
    expect((float) $active->fresh()->remaining_qty)->toBe(60.0);

    // On-hand drops by the written-off quantity only.
    expect((int) Stock::withoutGlobalScopes()->first()->quantity)->toBe(60);

    // The recalled lot's cost layer is consumed.
    expect((int) StockLayer::withoutGlobalScopes()->where('batch_id', $recalled->id)->sum('qty_remaining'))->toBe(0);

    // A DAMAGE movement was recorded and posted to the GL.
    $movement = StockMovement::withoutGlobalScopes()
        ->where('type', \App\Enums\ChangeTypeEnum::DAMAGE)
        ->first();

    expect($movement)->not->toBeNull();
    expect((int) $movement->quantity)->toBe(40);
    expect($movement->gl_journal_id)->not->toBeNull();
});

it('rejects writing off a batch that has no remaining quantity', function () {
    $batch = Batch::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'batch_no' => 'B-EMPTY',
        'initial_qty' => 0,
        'remaining_qty' => 0,
        'unit_cost' => 10,
        'status' => BatchStatusEnum::Recalled,
    ]);

    $this->postJson("/api/admin/batch/{$batch->id}/write-off")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('batch_id');
});

it('allows a damage report to be approved against a recalled batch', function () {
    [, $recalled] = seedWriteOffScenario($this, BatchStatusEnum::Recalled, activeQty: 60, heldQty: 40);

    $this->postJson('/api/admin/damage-report', [
        'date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'status' => 'approved',
        'reason' => 'Recalled goods disposal',
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'quantity' => 40,
            'batch_id' => $recalled->id,
        ]],
    ])->assertCreated();

    expect((float) $recalled->fresh()->remaining_qty)->toBe(0.0);
    expect((int) Stock::withoutGlobalScopes()->first()->quantity)->toBe(60);
});

it('still blocks selling a held batch', function () {
    [, $recalled] = seedWriteOffScenario($this, BatchStatusEnum::Recalled, activeQty: 60, heldQty: 40);

    $issue = app(InventoryLayerIssueService::class);

    expect(fn () => $issue->issue(
        $this->company,
        $recalled,
        (int) $this->variant->id,
        (int) $this->warehouse->id,
        10,
        \App\Enums\ChangeTypeEnum::SALE,
        $this->user->id,
        null,
        $recalled->id,
    ))->toThrow(ValidationException::class);
});

it('permits disposal of a quarantined batch when allowHeldBatch is set', function () {
    [, $quarantined] = seedWriteOffScenario($this, BatchStatusEnum::Quarantine, activeQty: 60, heldQty: 40);

    $reference = \App\Models\DamageReport::create([
        'company_id' => $this->company->id,
        'date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'create_user_id' => $this->user->id,
        'status' => \App\Enums\StatusEnum::APPROVED,
    ]);

    $issue = app(InventoryLayerIssueService::class);

    $movement = $issue->issue(
        $this->company,
        $reference,
        (int) $this->variant->id,
        (int) $this->warehouse->id,
        40,
        \App\Enums\ChangeTypeEnum::ADJUSTMENT_OUT,
        $this->user->id,
        null,
        $quarantined->id,
        allowHeldBatch: true,
    );

    expect((int) $movement->quantity)->toBe(40);
    expect((int) StockLayer::withoutGlobalScopes()->where('batch_id', $quarantined->id)->sum('qty_remaining'))->toBe(0);
});
