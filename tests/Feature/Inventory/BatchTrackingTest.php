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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

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
        'company_name' => 'Batch Co',
        'code' => 'BC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'name' => 'Main Branch',
        'code' => 'BR1',
    ]);

    // Establish branch context before master data is created so branch-owned
    // rows (products, variants, warehouses) are stamped with this branch.
    TenantService::setCompanyId($this->company->id);
    TenantService::setBranchId($this->branch->id);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Batch Admin',
        'email' => 'batch-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->warehouseA = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Warehouse A',
        'code' => 'WA',
    ]);

    $this->warehouseB = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Warehouse B',
        'code' => 'WB',
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Tracked Widget',
        'code' => 'TW',
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'SKU-TRACK',
        'is_default' => true,
        'is_batch_tracked' => true,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
    TenantService::setBranchId($this->branch->id);
});

/**
 * Create a batch with a backing stock layer so batch and ledger agree.
 */
function seedBatch(object $test, int $warehouseId, array $overrides = []): Batch
{
    $qty = $overrides['qty'] ?? 100;

    $batch = Batch::create(array_merge([
        'company_id' => $test->company->id,
        'product_variant_id' => $test->variant->id,
        'warehouse_id' => $warehouseId,
        'batch_no' => 'B-'.uniqid(),
        'initial_qty' => 0,
        'remaining_qty' => 0,
        'unit_cost' => 10,
        'status' => BatchStatusEnum::Active,
    ], array_diff_key($overrides, ['qty' => true])));

    Stock::withoutGlobalScopes()->updateOrCreate(
        [
            'company_id' => $test->company->id,
            'product_variant_id' => $test->variant->id,
            'warehouse_id' => $warehouseId,
        ],
        ['quantity' => $qty, 'on_hold' => 0, 'branch_id' => $test->branch->id],
    );

    StockLayer::create([
        'company_id' => $test->company->id,
        'product_variant_id' => $test->variant->id,
        'warehouse_id' => $warehouseId,
        'qty_remaining' => $qty,
        'unit_cost' => 10,
        'received_at' => now(),
        'batch_id' => $batch->id,
    ]);

    Batch::reconcileRemaining($batch->id);

    return $batch->fresh();
}

it('derives batch remaining quantity from the stock ledger', function () {
    $batch = seedBatch($this, $this->warehouseA->id, ['qty' => 40]);

    expect((float) $batch->remaining_qty)->toBe(40.0)
        ->and($batch->status)->toBe(BatchStatusEnum::Active);
});

it('orders FEFO batches by expiry and excludes expired ones', function () {
    $late = seedBatch($this, $this->warehouseA->id, [
        'batch_no' => 'LATE',
        'expiry_date' => now()->addDays(60)->toDateString(),
        'qty' => 10,
    ]);
    $early = seedBatch($this, $this->warehouseA->id, [
        'batch_no' => 'EARLY',
        'expiry_date' => now()->addDays(10)->toDateString(),
        'qty' => 10,
    ]);
    seedBatch($this, $this->warehouseA->id, [
        'batch_no' => 'EXPIRED',
        'expiry_date' => now()->subDays(5)->toDateString(),
        'qty' => 10,
    ]);

    $response = $this->getJson(
        "/api/admin/batch/fefo?product_variant_id={$this->variant->id}&warehouse_id={$this->warehouseA->id}"
    );

    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toBe([$early->id, $late->id]);
});

it('keeps batch records warehouse-bound when stock is transferred', function () {
    $batchA = seedBatch($this, $this->warehouseA->id, ['batch_no' => 'TRANSFER-1', 'qty' => 100]);

    $response = $this->postJson('/api/admin/stock-transfer', [
        'date' => '2026-06-01',
        'to_warehouse_id' => $this->warehouseB->id,
        'status' => 'approved',
        'items' => [
            [
                'product_variant_id' => $this->variant->id,
                'from_warehouse_id' => $this->warehouseA->id,
                'quantity' => 30,
                'batch_id' => $batchA->id,
            ],
        ],
    ]);

    $response->assertCreated();

    // Source batch reduced to 70.
    expect((float) $batchA->fresh()->remaining_qty)->toBe(70.0);

    // A destination-warehouse batch now exists with the transferred quantity.
    $batchB = Batch::where('warehouse_id', $this->warehouseB->id)
        ->where('batch_no', 'TRANSFER-1')
        ->first();

    expect($batchB)->not->toBeNull()
        ->and((float) $batchB->remaining_qty)->toBe(30.0);

    // The destination cost layer points at the destination batch, not the source.
    $layerBatchId = StockLayer::withoutGlobalScopes()
        ->where('warehouse_id', $this->warehouseB->id)
        ->where('product_variant_id', $this->variant->id)
        ->value('batch_id');

    expect($layerBatchId)->toBe($batchB->id);
});

it('reconciles drifted batch quantities from the ledger via command', function () {
    $batch = seedBatch($this, $this->warehouseA->id, ['qty' => 25]);

    // Simulate drift between the cached column and the ledger.
    Batch::where('id', $batch->id)->update(['remaining_qty' => 999]);

    $this->artisan('batch:reconcile')->assertSuccessful();

    expect((float) $batch->fresh()->remaining_qty)->toBe(25.0);
});

it('requires a batch when the variant is batch-tracked', function () {
    $response = $this->postJson('/api/admin/opening-stock-entry', [
        'reference_no' => 'OSE-NB',
        'date' => '2026-01-15',
        'warehouse_id' => $this->warehouseA->id,
        'status' => 'draft',
        'items' => [
            [
                'product_variant_id' => $this->variant->id,
                'quantity' => 10,
                'unit_cost' => 12.5,
            ],
        ],
    ]);

    $response->assertUnprocessable();
    expect($response->json('errors'))->toHaveKey('items.0.batch_id');
});

it('rejects a batch that belongs to a different warehouse', function () {
    $otherWarehouseBatch = seedBatch($this, $this->warehouseB->id, ['batch_no' => 'WRONG-WH']);

    $response = $this->postJson('/api/admin/opening-stock-entry', [
        'reference_no' => 'OSE-WW',
        'date' => '2026-01-15',
        'warehouse_id' => $this->warehouseA->id,
        'status' => 'draft',
        'items' => [
            [
                'product_variant_id' => $this->variant->id,
                'quantity' => 10,
                'unit_cost' => 12.5,
                'batch_id' => $otherWarehouseBatch->id,
            ],
        ],
    ]);

    $response->assertUnprocessable();
    expect($response->json('errors'))->toHaveKey('items.0.batch_id');
});

it('blocks issuing stock from a quarantined batch', function () {
    $batchA = seedBatch($this, $this->warehouseA->id, ['batch_no' => 'HOLD', 'qty' => 50]);
    $batchA->update(['status' => BatchStatusEnum::Quarantine]);

    $response = $this->postJson('/api/admin/stock-transfer', [
        'date' => '2026-06-01',
        'to_warehouse_id' => $this->warehouseB->id,
        'status' => 'approved',
        'items' => [
            [
                'product_variant_id' => $this->variant->id,
                'from_warehouse_id' => $this->warehouseA->id,
                'quantity' => 10,
                'batch_id' => $batchA->id,
            ],
        ],
    ]);

    $response->assertUnprocessable();
    expect($response->json('errors'))->toHaveKey('batch_id');
});
