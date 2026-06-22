<?php

use App\Models\Bom;
use App\Models\User;
use App\Models\Party;
use App\Models\Stock;
use App\Models\BomItem;
use App\Models\Company;
use App\Models\Product;
use App\Enums\StatusEnum;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Models\SalesOrder;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\ProductVariant;
use App\Models\SalesOrderItem;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function mrpWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
}

function mrpVariant(object $test, string $name, string $code, float $minStock = 0): ProductVariant
{
    $product = Product::create([
        'company_id' => $test->company->id, 'name' => $name, 'code' => $code,
        'min_stock_level' => $minStock,
    ]);

    return ProductVariant::create([
        'company_id' => $test->company->id, 'product_id' => $product->id,
        'sku' => $code.'-1', 'is_default' => true, 'purchase_price' => 1.0,
    ]);
}

function mrpSetStock(object $test, ProductVariant $variant, float $qty): void
{
    Stock::create([
        'company_id' => $test->company->id,
        'product_variant_id' => $variant->id,
        'warehouse_id' => $test->warehouse->id,
        'quantity' => $qty,
        'on_hold' => 0,
    ]);
}

beforeEach(function () {
    mrpWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);
    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'MRP Co', 'code' => 'MRP',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);
    $this->user = User::create([
        'company_id' => $this->company->id, 'name' => 'MRP Tester',
        'email' => 'mrp-'.uniqid().'@example.com',
        'password' => bcrypt('password'), 'user_type' => UserTypeEnum::ADMIN,
    ]);
    $this->warehouse = Warehouse::create(['company_id' => $this->company->id, 'name' => 'Main', 'code' => 'W1']);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

it('suggests a production order with an exploded material shortfall for a manufacturable item below minimum', function () {
    // Finished good "Chair" min 10, on hand 2 → shortfall 8. BOM: 4 legs + 1 seat per chair.
    $leg = mrpVariant($this, 'Leg', 'LEG');
    $seat = mrpVariant($this, 'Seat', 'SEAT');
    $chair = mrpVariant($this, 'Chair', 'CHAIR', 10);
    mrpSetStock($this, $chair, 2);
    mrpSetStock($this, $leg, 5);   // need 4×8 = 32 → short 27
    mrpSetStock($this, $seat, 20); // need 1×8 = 8 → covered

    $bom = Bom::create([
        'company_id' => $this->company->id, 'product_variant_id' => $chair->id,
        'name' => 'Chair BOM', 'version' => '1', 'output_qty' => 1, 'is_active' => true, 'is_default' => true,
    ]);
    BomItem::create(['bom_id' => $bom->id, 'product_variant_id' => $leg->id, 'quantity' => 4, 'item_type' => 'material']);
    BomItem::create(['bom_id' => $bom->id, 'product_variant_id' => $seat->id, 'quantity' => 1, 'item_type' => 'material']);

    $response = $this->getJson('/api/admin/inventory-report/mrp-plan');
    $response->assertSuccessful();

    $chairSuggestion = collect($response->json('data.suggestions'))->firstWhere('variant_id', $chair->id);
    expect($chairSuggestion)->not->toBeNull();
    expect($chairSuggestion['action'])->toBe('produce');
    expect((float) $chairSuggestion['shortfall'])->toBe(8.0);
    expect($chairSuggestion['bom_id'])->toBe($bom->id);

    $legReq = collect($chairSuggestion['material_requirements'])->firstWhere('variant_id', $leg->id);
    expect((float) $legReq['required_qty'])->toBe(32.0); // 4 × 8
    expect((float) $legReq['shortfall'])->toBe(27.0);    // 32 - 5
    expect($legReq['action'])->toBe('purchase');

    $seatReq = collect($chairSuggestion['material_requirements'])->firstWhere('variant_id', $seat->id);
    expect((float) $seatReq['shortfall'])->toBe(0.0);    // 8 needed, 20 on hand
    expect($seatReq['action'])->toBe('ok');
});

it('suggests a purchase for a non-manufactured item below minimum', function () {
    $bolt = mrpVariant($this, 'Bolt', 'BOLT', 100);
    mrpSetStock($this, $bolt, 30);

    $response = $this->getJson('/api/admin/inventory-report/mrp-plan');
    $response->assertSuccessful();

    $suggestion = collect($response->json('data.suggestions'))->firstWhere('variant_id', $bolt->id);
    expect($suggestion['action'])->toBe('purchase');
    expect((float) $suggestion['shortfall'])->toBe(70.0);
    expect($suggestion['bom_id'])->toBeNull();
});

it('excludes items at or above their minimum and items with no minimum', function () {
    $healthy = mrpVariant($this, 'Healthy', 'HLT', 5);
    mrpSetStock($this, $healthy, 10); // above min

    $noMin = mrpVariant($this, 'NoMin', 'NOMIN', 0);
    mrpSetStock($this, $noMin, 0);

    $response = $this->getJson('/api/admin/inventory-report/mrp-plan');
    $response->assertSuccessful();

    $ids = collect($response->json('data.suggestions'))->pluck('variant_id')->all();
    expect($ids)->not->toContain($healthy->id);
    expect($ids)->not->toContain($noMin->id);
});

function mrpSalesOrder(object $test, ProductVariant $variant, int $qty, string $status): void
{
    $party = Party::create([
        'company_id' => $test->company->id, 'name' => 'Cust '.uniqid(),
        'code' => 'C-'.uniqid(), 'type' => PartyTypeEnum::CUSTOMER,
    ]);
    $order = SalesOrder::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'party_id' => $party->id,
        'order_no' => 'SO-'.uniqid(),
        'order_date' => now()->toDateString(),
        'create_user_id' => $test->user->id,
        'status' => $status,
    ]);
    SalesOrderItem::create([
        'sales_order_id' => $order->id,
        'product_variant_id' => $variant->id,
        'quantity' => $qty,
    ]);
}

it('adds open sales-order demand on top of the minimum stock buffer', function () {
    $gadget = mrpVariant($this, 'Gadget', 'GAD', 5);
    mrpSetStock($this, $gadget, 8);              // above min on its own
    mrpSalesOrder($this, $gadget, 10, StatusEnum::APPROVED->value); // demand 10

    $response = $this->getJson('/api/admin/inventory-report/mrp-plan');
    $suggestion = collect($response->json('data.suggestions'))->firstWhere('variant_id', $gadget->id);

    // requirement = min 5 + demand 10 = 15; available 8 → shortfall 7
    expect($suggestion)->not->toBeNull();
    expect((float) $suggestion['open_demand'])->toBe(10.0);
    expect((float) $suggestion['requirement'])->toBe(15.0);
    expect((float) $suggestion['shortfall'])->toBe(7.0);
});

it('ignores draft sales orders as demand', function () {
    $gizmo = mrpVariant($this, 'Gizmo', 'GIZ', 0);
    mrpSetStock($this, $gizmo, 3);
    mrpSalesOrder($this, $gizmo, 20, StatusEnum::DRAFT->value);

    $response = $this->getJson('/api/admin/inventory-report/mrp-plan');
    $ids = collect($response->json('data.suggestions'))->pluck('variant_id')->all();

    // no min stock + only draft demand → not a candidate
    expect($ids)->not->toContain($gizmo->id);
});

it('counts held stock as unavailable when measuring the shortfall', function () {
    $widget = mrpVariant($this, 'Widget', 'WID', 10);
    Stock::create([
        'company_id' => $this->company->id, 'product_variant_id' => $widget->id,
        'warehouse_id' => $this->warehouse->id, 'quantity' => 8, 'on_hold' => 6,
    ]);

    $response = $this->getJson('/api/admin/inventory-report/mrp-plan');
    $suggestion = collect($response->json('data.suggestions'))->firstWhere('variant_id', $widget->id);

    // available = 8 - 6 = 2 → shortfall 8
    expect((float) $suggestion['on_hand'])->toBe(2.0);
    expect((float) $suggestion['shortfall'])->toBe(8.0);
});
