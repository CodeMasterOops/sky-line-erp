<?php

use App\Models\User;
use App\Models\Stock;
use App\Models\Company;
use App\Models\Product;
use App\Enums\StatusEnum;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Models\StockLayer;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\StockAdjustment;
use App\Models\ProductVariant;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Test Co',
        'code' => 'TC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Tester',
        'email' => 'tester-' . uniqid() . '@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Main Warehouse',
        'code' => 'MW',
    ]);

    $this->warehouseB = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Branch Warehouse',
        'code' => 'BW',
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Widget',
        'code' => 'WIDGET',
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'SKU-1',
        'is_default' => true,
    ]);

    Stock::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 50,
    ]);

    StockLayer::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 50,
        'qty_remaining' => 50,
        'unit_cost' => 20.00,
        'received_at' => now(),
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
});

it('creates a draft stock adjustment with per-item warehouse_id', function () {
    $response = $this->postJson('/api/admin/stock-adjustment', [
        'date' => '2026-06-01',
        'status' => 'draft',
        'items' => [
            [
                'warehouse_id' => $this->warehouse->id,
                'product_variant_id' => $this->variant->id,
                'direction' => 'in',
                'quantity' => 10,
                'unit_cost' => 25.00,
            ],
        ],
    ]);

    $response->assertCreated();
    expect($response->json('data.items.0.warehouse_id'))->toBe($this->warehouse->id);
    expect($response->json('data.items.0.warehouse_name'))->toBe('Main Warehouse');

    $this->assertDatabaseHas('stock_adjustment_items', [
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'direction' => 'in',
        'quantity' => 10,
    ]);
});

it('creates and approves an adjustment IN, increasing stock in the per-item warehouse', function () {
    $response = $this->postJson('/api/admin/stock-adjustment', [
        'date' => '2026-06-01',
        'status' => 'approved',
        'items' => [
            [
                'warehouse_id' => $this->warehouseB->id,
                'product_variant_id' => $this->variant->id,
                'direction' => 'in',
                'quantity' => 15,
                'unit_cost' => 30.00,
            ],
        ],
    ]);

    $response->assertCreated();
    expect($response->json('data.status'))->toBe(StatusEnum::APPROVED->value);

    expect((int) Stock::where('company_id', $this->company->id)
        ->where('product_variant_id', $this->variant->id)
        ->where('warehouse_id', $this->warehouseB->id)
        ->value('quantity')
    )->toBe(15);
});

it('creates and approves an adjustment OUT, reducing stock in the per-item warehouse', function () {
    $response = $this->postJson('/api/admin/stock-adjustment', [
        'date' => '2026-06-01',
        'status' => 'approved',
        'items' => [
            [
                'warehouse_id' => $this->warehouse->id,
                'product_variant_id' => $this->variant->id,
                'direction' => 'out',
                'quantity' => 20,
            ],
        ],
    ]);

    $response->assertCreated();
    expect($response->json('data.status'))->toBe(StatusEnum::APPROVED->value);

    expect((int) Stock::where('company_id', $this->company->id)
        ->where('product_variant_id', $this->variant->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->value('quantity')
    )->toBe(30);
});

it('rejects an adjustment when items.*.warehouse_id is missing', function () {
    $response = $this->postJson('/api/admin/stock-adjustment', [
        'date' => '2026-06-01',
        'status' => 'draft',
        'items' => [
            [
                'product_variant_id' => $this->variant->id,
                'direction' => 'in',
                'quantity' => 5,
                'unit_cost' => 10.00,
            ],
        ],
    ]);

    $response->assertUnprocessable();
    expect($response->json('errors'))->toHaveKey('items.0.warehouse_id');
});

it('rejects duplicate product and warehouse combination in the same adjustment', function () {
    $response = $this->postJson('/api/admin/stock-adjustment', [
        'date' => '2026-06-01',
        'status' => 'draft',
        'items' => [
            [
                'warehouse_id' => $this->warehouse->id,
                'product_variant_id' => $this->variant->id,
                'direction' => 'in',
                'quantity' => 5,
                'unit_cost' => 10.00,
            ],
            [
                'warehouse_id' => $this->warehouse->id,
                'product_variant_id' => $this->variant->id,
                'direction' => 'out',
                'quantity' => 3,
            ],
        ],
    ]);

    $response->assertUnprocessable();
});

it('allows the same product in different warehouses in one adjustment', function () {
    $response = $this->postJson('/api/admin/stock-adjustment', [
        'date' => '2026-06-01',
        'status' => 'draft',
        'items' => [
            [
                'warehouse_id' => $this->warehouse->id,
                'product_variant_id' => $this->variant->id,
                'direction' => 'out',
                'quantity' => 5,
            ],
            [
                'warehouse_id' => $this->warehouseB->id,
                'product_variant_id' => $this->variant->id,
                'direction' => 'in',
                'quantity' => 5,
                'unit_cost' => 10.00,
            ],
        ],
    ]);

    $response->assertCreated();
    expect($response->json('data.items'))->toHaveCount(2);
});

it('updates a draft adjustment replacing items with per-item warehouse_id', function () {
    $adjustment = StockAdjustment::create([
        'company_id' => $this->company->id,
        'date' => '2026-06-01',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $adjustment->stockAdjustmentItems()->create([
        'company_id' => $this->company->id,
        'warehouse_id' => $this->warehouse->id,
        'product_variant_id' => $this->variant->id,
        'direction' => 'in',
        'quantity' => 5,
        'unit_cost' => 10.00,
    ]);

    $response = $this->putJson("/api/admin/stock-adjustment/{$adjustment->id}", [
        'date' => '2026-06-02',
        'status' => 'draft',
        'items' => [
            [
                'warehouse_id' => $this->warehouseB->id,
                'product_variant_id' => $this->variant->id,
                'direction' => 'in',
                'quantity' => 20,
                'unit_cost' => 15.00,
            ],
        ],
    ]);

    $response->assertOk();
    expect($response->json('data.items.0.quantity'))->toBe(20);
    expect($response->json('data.items.0.warehouse_id'))->toBe($this->warehouseB->id);
});

it('approves a draft adjustment via the approve endpoint', function () {
    $adjustment = StockAdjustment::create([
        'company_id' => $this->company->id,
        'date' => '2026-06-01',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $adjustment->stockAdjustmentItems()->create([
        'company_id' => $this->company->id,
        'warehouse_id' => $this->warehouse->id,
        'product_variant_id' => $this->variant->id,
        'direction' => 'out',
        'quantity' => 10,
    ]);

    $response = $this->postJson("/api/admin/stock-adjustment/{$adjustment->id}/approve");

    $response->assertOk();
    expect($response->json('data.status'))->toBe(StatusEnum::APPROVED->value);

    expect((int) Stock::where('product_variant_id', $this->variant->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->value('quantity')
    )->toBe(40);
});
