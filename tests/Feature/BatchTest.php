<?php

use App\Models\User;
use App\Models\Batch;
use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function batchWarmAllTablesCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
}

function batchPayload(object $test, array $overrides = []): array
{
    return array_merge([
        'product_variant_id' => $test->variant->id,
        'warehouse_id' => $test->warehouse->id,
        'batch_no' => 'B-100',
        'initial_qty' => 10,
        'unit_cost' => 25,
    ], $overrides);
}

beforeEach(function () {
    batchWarmAllTablesCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Batch Test Co',
        'code' => 'BTC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Batch Tester',
        'email' => 'batch-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Main',
        'code' => 'W1',
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Widget',
        'code' => 'WIDGET-BATCH',
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'SKU-BATCH-1',
        'sales_price' => 100,
        'is_batch_tracked' => true,
        'is_default' => true,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

it('creates a batch for a batch-tracked variant', function () {
    $this->postJson('/api/admin/batch', batchPayload($this))->assertCreated();

    expect(Batch::withoutGlobalScopes()->count())->toBe(1);
    expect((float) Batch::withoutGlobalScopes()->first()->remaining_qty)->toBe(10.0);
});

it('variant search returns only batch-tracked variants when batch_tracked_only is set', function () {
    ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'SKU-PLAIN-SEARCH',
        'sales_price' => 100,
        'is_batch_tracked' => false,
    ]);

    $response = $this->getJson('/api/admin/product/variant/search?batch_tracked_only=1');

    $response->assertSuccessful();

    $skus = collect($response->json('data'))->pluck('sku');
    expect($skus)->toContain('SKU-BATCH-1');
    expect($skus)->not->toContain('SKU-PLAIN-SEARCH');
});

it('serializes mfg and expiry dates as Y-m-d without a time component', function () {
    $this->postJson('/api/admin/batch', batchPayload($this, [
        'mfg_date' => '2026-06-15',
        'expiry_date' => '2026-06-18',
    ]))->assertCreated();

    $this->getJson('/api/admin/batch')
        ->assertOk()
        ->assertJsonPath('data.0.mfg_date', '2026-06-15')
        ->assertJsonPath('data.0.expiry_date', '2026-06-18');
});

it('rejects creating a batch for a non-batch-tracked variant', function () {
    $plainVariant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'SKU-BATCH-PLAIN',
        'sales_price' => 100,
        'is_batch_tracked' => false,
    ]);

    $this->postJson('/api/admin/batch', batchPayload($this, [
        'product_variant_id' => $plainVariant->id,
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors('product_variant_id');

    expect(Batch::withoutGlobalScopes()->count())->toBe(0);
});
