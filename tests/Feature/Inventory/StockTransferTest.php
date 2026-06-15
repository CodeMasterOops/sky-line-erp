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
use App\Models\StockTransfer;
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
        'email' => 'tester-'.uniqid().'@example.com',
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
        'warehouse_id' => $this->warehouseA->id,
        'quantity' => 100,
    ]);

    StockLayer::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouseA->id,
        'quantity' => 100,
        'qty_remaining' => 100,
        'unit_cost' => 10.00,
        'received_at' => now(),
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
});

it('creates a draft stock transfer with per-item from_warehouse_id', function () {
    $response = $this->postJson('/api/admin/stock-transfer', [
        'date' => '2026-06-01',
        'to_warehouse_id' => $this->warehouseB->id,
        'status' => 'draft',
        'items' => [
            [
                'product_variant_id' => $this->variant->id,
                'from_warehouse_id' => $this->warehouseA->id,
                'quantity' => 10,
            ],
        ],
    ]);

    $response->assertCreated();
    expect($response->json('data.items.0.from_warehouse_id'))->toBe($this->warehouseA->id);
    expect($response->json('data.items.0.from_warehouse_name'))->toBe('Warehouse A');

    $this->assertDatabaseHas('stock_transfer_items', [
        'product_variant_id' => $this->variant->id,
        'from_warehouse_id' => $this->warehouseA->id,
        'quantity' => 10,
    ]);
});

it('creates and approves a stock transfer, moving inventory correctly', function () {
    $response = $this->postJson('/api/admin/stock-transfer', [
        'date' => '2026-06-01',
        'to_warehouse_id' => $this->warehouseB->id,
        'status' => 'approved',
        'items' => [
            [
                'product_variant_id' => $this->variant->id,
                'from_warehouse_id' => $this->warehouseA->id,
                'quantity' => 20,
            ],
        ],
    ]);

    $response->assertCreated();
    expect($response->json('data.status'))->toBe(StatusEnum::APPROVED->value);

    expect((int) Stock::where('company_id', $this->company->id)
        ->where('product_variant_id', $this->variant->id)
        ->where('warehouse_id', $this->warehouseA->id)
        ->value('quantity')
    )->toBe(80);

    expect((int) Stock::where('company_id', $this->company->id)
        ->where('product_variant_id', $this->variant->id)
        ->where('warehouse_id', $this->warehouseB->id)
        ->value('quantity')
    )->toBe(20);
});

it('rejects a transfer when from_warehouse_id equals to_warehouse_id', function () {
    $response = $this->postJson('/api/admin/stock-transfer', [
        'date' => '2026-06-01',
        'to_warehouse_id' => $this->warehouseA->id,
        'status' => 'draft',
        'items' => [
            [
                'product_variant_id' => $this->variant->id,
                'from_warehouse_id' => $this->warehouseA->id,
                'quantity' => 5,
            ],
        ],
    ]);

    $response->assertUnprocessable();
});

it('rejects a transfer when items are missing from_warehouse_id', function () {
    $response = $this->postJson('/api/admin/stock-transfer', [
        'date' => '2026-06-01',
        'to_warehouse_id' => $this->warehouseB->id,
        'status' => 'draft',
        'items' => [
            [
                'product_variant_id' => $this->variant->id,
                'quantity' => 5,
            ],
        ],
    ]);

    $response->assertUnprocessable();
    expect($response->json('errors'))->toHaveKey('items.0.from_warehouse_id');
});

it('updates a draft transfer replacing items with new per-item from_warehouse_id', function () {
    $transfer = StockTransfer::create([
        'company_id' => $this->company->id,
        'date' => '2026-06-01',
        'to_warehouse_id' => $this->warehouseB->id,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $transfer->stockTransferItems()->create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'from_warehouse_id' => $this->warehouseA->id,
        'quantity' => 5,
    ]);

    $response = $this->putJson("/api/admin/stock-transfer/{$transfer->id}", [
        'date' => '2026-06-02',
        'to_warehouse_id' => $this->warehouseB->id,
        'status' => 'draft',
        'items' => [
            [
                'product_variant_id' => $this->variant->id,
                'from_warehouse_id' => $this->warehouseA->id,
                'quantity' => 15,
            ],
        ],
    ]);

    $response->assertOk();
    expect($response->json('data.items.0.quantity'))->toBe(15);
    expect($response->json('data.items.0.from_warehouse_id'))->toBe($this->warehouseA->id);
});

it('approves a draft transfer via the approve endpoint', function () {
    $transfer = StockTransfer::create([
        'company_id' => $this->company->id,
        'date' => '2026-06-01',
        'to_warehouse_id' => $this->warehouseB->id,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $transfer->stockTransferItems()->create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'from_warehouse_id' => $this->warehouseA->id,
        'quantity' => 30,
    ]);

    $response = $this->postJson("/api/admin/stock-transfer/{$transfer->id}/approve");

    $response->assertOk();
    expect($response->json('data.status'))->toBe(StatusEnum::APPROVED->value);

    expect((int) Stock::where('product_variant_id', $this->variant->id)
        ->where('warehouse_id', $this->warehouseA->id)
        ->value('quantity')
    )->toBe(70);

    expect((int) Stock::where('product_variant_id', $this->variant->id)
        ->where('warehouse_id', $this->warehouseB->id)
        ->value('quantity')
    )->toBe(30);
});
