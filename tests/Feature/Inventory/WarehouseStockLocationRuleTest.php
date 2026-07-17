<?php

use App\Models\User;
use App\Models\Stock;
use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Models\StockLayer;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
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

    $this->groupWarehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Main',
        'code' => 'MAIN',
    ]);

    $this->leaf = Warehouse::create([
        'company_id' => $this->company->id,
        'parent_id' => $this->groupWarehouse->id,
        'name' => 'Shelf A',
        'code' => 'SHELF-A',
    ]);

    $this->otherLeaf = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Standalone',
        'code' => 'STAND',
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
        'warehouse_id' => $this->leaf->id,
        'quantity' => 100,
    ]);

    StockLayer::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->leaf->id,
        'quantity' => 100,
        'qty_remaining' => 100,
        'unit_cost' => 10.00,
        'received_at' => now(),
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
});

it('rejects a stock transfer into a group (parent) warehouse', function () {
    $response = $this->postJson('/api/admin/stock-transfer', [
        'date' => '2026-06-01',
        'to_warehouse_id' => $this->groupWarehouse->id,
        'status' => 'draft',
        'items' => [
            [
                'product_variant_id' => $this->variant->id,
                'from_warehouse_id' => $this->leaf->id,
                'quantity' => 10,
            ],
        ],
    ]);

    $response->assertUnprocessable();
    expect($response->json('errors'))->toHaveKey('to_warehouse_id');
});

it('allows a stock transfer into a leaf warehouse', function () {
    $response = $this->postJson('/api/admin/stock-transfer', [
        'date' => '2026-06-01',
        'to_warehouse_id' => $this->otherLeaf->id,
        'status' => 'draft',
        'items' => [
            [
                'product_variant_id' => $this->variant->id,
                'from_warehouse_id' => $this->leaf->id,
                'quantity' => 10,
            ],
        ],
    ]);

    $response->assertCreated();
});

it('rejects a stock adjustment on a group (parent) warehouse', function () {
    $response = $this->postJson('/api/admin/stock-adjustment', [
        'date' => '2026-06-01',
        'status' => 'draft',
        'items' => [
            [
                'product_variant_id' => $this->variant->id,
                'warehouse_id' => $this->groupWarehouse->id,
                'quantity' => 5,
                'direction' => 'in',
            ],
        ],
    ]);

    $response->assertUnprocessable();
    expect($response->json('errors'))->toHaveKey('items.0.warehouse_id');
});

it('reports stock held on a group warehouse via the check command', function () {
    Stock::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->groupWarehouse->id,
        'quantity' => 7,
    ]);

    $this->artisan('warehouses:check-stock-locations')
        ->assertExitCode(1);
});

it('passes the check command when all stock is on leaves', function () {
    $this->artisan('warehouses:check-stock-locations')
        ->assertExitCode(0);
});
