<?php

use App\Models\User;
use App\Models\Batch;
use App\Models\Stock;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Models\StockLayer;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Enums\BatchStatusEnum;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use Illuminate\Validation\ValidationException;
use App\Services\Inventory\StockQuantityService;

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
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Held Stock Co',
        'code' => 'HSC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'name' => 'Main Branch',
        'code' => 'BR1',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Held Admin',
        'email' => 'held-'.uniqid().'@example.com',
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
        'sku' => 'SKU-HELD',
        'is_default' => true,
        'is_batch_tracked' => true,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
    TenantService::setBranchId($this->branch->id);
});

/**
 * Seed one on-hand stock row plus an active and a held batch (with backing layers)
 * so the ledger and lot records agree. Returns the active + held batches.
 */
function seedHeldScenario(object $test, BatchStatusEnum $heldStatus, int $activeQty = 60, int $heldQty = 40): array
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

it('sums held quantity across recalled, quarantine and expired lots only', function () {
    seedHeldScenario($this, BatchStatusEnum::Recalled, activeQty: 60, heldQty: 40);

    $held = Batch::heldQuantity($this->company->id, $this->variant->id, $this->warehouse->id);

    expect($held)->toBe(40.0);
})->with([
    'recalled' => [BatchStatusEnum::Recalled],
    'quarantine' => [BatchStatusEnum::Quarantine],
    'expired' => [BatchStatusEnum::Expired],
]);

it('excludes active lots from held quantity', function () {
    Batch::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'batch_no' => 'B-ACTIVE',
        'initial_qty' => 50,
        'remaining_qty' => 50,
        'unit_cost' => 10,
        'status' => BatchStatusEnum::Active,
    ]);

    expect(Batch::heldQuantity($this->company->id, $this->variant->id, $this->warehouse->id))->toBe(0.0);
});

it('cannot reserve against held stock beyond the available quantity', function () {
    seedHeldScenario($this, BatchStatusEnum::Recalled, activeQty: 60, heldQty: 40);

    // On-hand is 100, but 40 is recalled -> available is 60. Reserving 70 must fail.
    expect(function () {
        DB::transaction(function () {
            $service = app(StockQuantityService::class);
            $service->lockForUpdateOrCreate($this->company->id, $this->variant->id, $this->warehouse->id);
            $service->holdOnHand($this->company->id, $this->variant->id, $this->warehouse->id, 70);
        });
    })->toThrow(ValidationException::class);

    expect((int) Stock::withoutGlobalScopes()->first()->on_hold)->toBe(0);
});

it('allows reserving up to the available (non-held) quantity', function () {
    seedHeldScenario($this, BatchStatusEnum::Recalled, activeQty: 60, heldQty: 40);

    DB::transaction(function () {
        $service = app(StockQuantityService::class);
        $service->lockForUpdateOrCreate($this->company->id, $this->variant->id, $this->warehouse->id);
        $service->holdOnHand($this->company->id, $this->variant->id, $this->warehouse->id, 60);
    });

    expect((int) Stock::withoutGlobalScopes()->first()->on_hold)->toBe(60);
});

it('reports on-hand, held and available separately in the warehouse stock report', function () {
    seedHeldScenario($this, BatchStatusEnum::Recalled, activeQty: 60, heldQty: 40);

    $this->getJson('/api/admin/inventory-report/warehouse-stock')
        ->assertOk()
        ->assertJsonPath('data.summary.total_quantity', 100)
        ->assertJsonPath('data.summary.total_held', 40)
        ->assertJsonPath('data.summary.total_available', 60)
        ->assertJsonPath('data.rows.0.items.0.quantity', 100)
        ->assertJsonPath('data.rows.0.items.0.held', 40)
        ->assertJsonPath('data.rows.0.items.0.available', 60);
});
