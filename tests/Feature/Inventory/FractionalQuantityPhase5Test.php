<?php

use App\Models\User;
use App\Models\Stock;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Models\StockLayer;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\StockMovement;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use App\Services\Inventory\StockLayerLedger;
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
        'company_name' => 'Fractional Co',
        'code' => 'FRC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'name' => 'Main Branch',
        'code' => 'BR1',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Fractional Admin',
        'email' => 'fr-'.uniqid().'@example.com',
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
        'name' => 'Bulk Sugar',
        'code' => 'SUGAR',
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'SKU-KG',
        'is_default' => true,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
    TenantService::setBranchId($this->branch->id);
});

it('keeps fractional quantities through stock adjustments', function () {
    $service = app(StockQuantityService::class);

    DB::transaction(function () use ($service) {
        $service->lockForUpdateOrCreate($this->company->id, $this->variant->id, $this->warehouse->id);
        $service->adjust($this->company->id, $this->variant->id, $this->warehouse->id, 2.5);
        $service->adjust($this->company->id, $this->variant->id, $this->warehouse->id, -1.25);
    });

    expect((float) Stock::withoutGlobalScopes()->first()->quantity)->toBe(1.25);
});

it('preserves fractional quantities through receipt and FIFO consume', function () {
    $ledger = app(StockLayerLedger::class);

    $ledger->receipt($this->company, $this->variant->id, $this->warehouse->id, 2.5, 4.0);

    expect((float) StockLayer::withoutGlobalScopes()->sum('qty_remaining'))->toBe(2.5);

    $lines = $ledger->consume($this->company, $this->variant->id, $this->warehouse->id, 1.25, null);

    expect((float) collect($lines)->sum('quantity'))->toBe(1.25);
    expect((float) StockLayer::withoutGlobalScopes()->sum('qty_remaining'))->toBe(1.25);
});

it('reserves fractional quantities and enforces fractional availability', function () {
    Stock::withoutGlobalScopes()->create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 2.5,
        'on_hold' => 0,
    ]);

    $service = app(StockQuantityService::class);

    DB::transaction(function () use ($service) {
        $service->lockForUpdateOrCreate($this->company->id, $this->variant->id, $this->warehouse->id);
        $service->holdOnHand($this->company->id, $this->variant->id, $this->warehouse->id, 1.5);
    });

    expect((float) Stock::withoutGlobalScopes()->first()->on_hold)->toBe(1.5);

    // Only 1.0 remains available; reserving another 1.5 must fail.
    expect(function () use ($service) {
        DB::transaction(function () use ($service) {
            $service->lockForUpdateOrCreate($this->company->id, $this->variant->id, $this->warehouse->id);
            $service->holdOnHand($this->company->id, $this->variant->id, $this->warehouse->id, 1.5);
        });
    })->toThrow(Illuminate\Validation\ValidationException::class);
});

it('accepts a fractional quantity through the damage report document flow', function () {
    // Seed 5.0 on-hand with a backing cost layer.
    app(StockLayerLedger::class)->receipt($this->company, $this->variant->id, $this->warehouse->id, 5.0, 4.0);
    app(StockQuantityService::class)->adjust($this->company->id, $this->variant->id, $this->warehouse->id, 5);

    $this->postJson('/api/admin/damage-report', [
        'date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'status' => 'approved',
        'reason' => 'Spillage',
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'quantity' => 0.5,
        ]],
    ])->assertCreated();

    expect((float) Stock::withoutGlobalScopes()->first()->quantity)->toBe(4.5);
    expect((float) StockMovement::withoutGlobalScopes()->where('type', \App\Enums\ChangeTypeEnum::DAMAGE)->value('quantity'))->toBe(0.5);
});
