<?php

use App\Models\User;
use App\Models\Batch;
use App\Models\Company;
use App\Models\Product;
use App\Models\AuditLog;
use App\Models\Warehouse;
use App\Models\FiscalYear;
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
        'company_name' => 'Transition Co',
        'code' => 'TRC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Transition Admin',
        'email' => 'tr-'.uniqid().'@example.com',
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
        'sku' => 'SKU-TR',
        'is_default' => true,
        'is_batch_tracked' => true,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

function makeBatch(object $test, BatchStatusEnum $status, int $qty = 50): Batch
{
    return Batch::create([
        'company_id' => $test->company->id,
        'product_variant_id' => $test->variant->id,
        'warehouse_id' => $test->warehouse->id,
        'batch_no' => 'B-'.uniqid(),
        'initial_qty' => $qty,
        'remaining_qty' => $qty,
        'unit_cost' => 10,
        'status' => $status,
    ]);
}

it('allows valid status transitions', function (BatchStatusEnum $from, BatchStatusEnum $to) {
    $batch = makeBatch($this, $from);

    $this->putJson("/api/admin/batch/{$batch->id}", ['status' => $to->value])
        ->assertOk()
        ->assertJsonPath('data.status', $to->value);

    expect($batch->fresh()->status)->toBe($to);
})->with([
    'active -> quarantine' => [BatchStatusEnum::Active, BatchStatusEnum::Quarantine],
    'active -> recalled' => [BatchStatusEnum::Active, BatchStatusEnum::Recalled],
    'quarantine -> active' => [BatchStatusEnum::Quarantine, BatchStatusEnum::Active],
    'quarantine -> recalled' => [BatchStatusEnum::Quarantine, BatchStatusEnum::Recalled],
]);

it('rejects invalid status transitions', function (BatchStatusEnum $from, BatchStatusEnum $to) {
    $batch = makeBatch($this, $from);

    $this->putJson("/api/admin/batch/{$batch->id}", ['status' => $to->value])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    expect($batch->fresh()->status)->toBe($from);
})->with([
    'recalled -> active' => [BatchStatusEnum::Recalled, BatchStatusEnum::Active],
    'expired -> active' => [BatchStatusEnum::Expired, BatchStatusEnum::Active],
    'depleted -> active' => [BatchStatusEnum::Depleted, BatchStatusEnum::Active],
    'active -> expired' => [BatchStatusEnum::Active, BatchStatusEnum::Expired],
    'active -> depleted' => [BatchStatusEnum::Active, BatchStatusEnum::Depleted],
    'quarantine -> expired' => [BatchStatusEnum::Quarantine, BatchStatusEnum::Expired],
]);

it('allows editing non-status fields without a transition check', function () {
    $batch = makeBatch($this, BatchStatusEnum::Recalled);

    $this->putJson("/api/admin/batch/{$batch->id}", ['remarks' => 'Updated note'])
        ->assertOk();

    expect($batch->fresh()->remarks)->toBe('Updated note');
    expect($batch->fresh()->status)->toBe(BatchStatusEnum::Recalled);
});

it('records an audit log with actor and old/new status when a batch is recalled', function () {
    $batch = makeBatch($this, BatchStatusEnum::Active);

    $this->putJson("/api/admin/batch/{$batch->id}", [
        'status' => BatchStatusEnum::Recalled->value,
        'remarks' => 'Supplier safety recall',
    ])->assertOk();

    $log = AuditLog::withoutGlobalScopes()
        ->where('auditable_type', $batch->getMorphClass())
        ->where('auditable_id', $batch->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->user_id)->toBe($this->user->id);
    expect($log->old_values['status'])->toBe(BatchStatusEnum::Active->value);
    expect($log->new_values['status'])->toBe(BatchStatusEnum::Recalled->value);
});

it('does not mutate batch status when reading expiry alerts', function () {
    $batch = makeBatch($this, BatchStatusEnum::Active);
    $batch->update(['expiry_date' => now()->subDay()->toDateString()]);

    $this->getJson('/api/admin/batch/expiry-alerts?days=30')->assertOk();

    expect($batch->fresh()->status)->toBe(BatchStatusEnum::Active);
});

it('flags expired lots only through the batch:expire command', function () {
    $batch = makeBatch($this, BatchStatusEnum::Active);
    $batch->update(['expiry_date' => now()->subDay()->toDateString()]);

    $this->artisan('batch:expire')->assertSuccessful();

    expect($batch->fresh()->status)->toBe(BatchStatusEnum::Expired);
});
