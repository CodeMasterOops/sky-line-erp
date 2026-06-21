<?php

use App\Models\Bom;
use App\Models\User;
use App\Models\Stock;
use App\Models\BomItem;
use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\ProductVariant;
use App\Models\ProductionOrder;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function subAsmWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
}

function subAsmVariant(object $test, string $name, string $code): ProductVariant
{
    $product = Product::create(['company_id' => $test->company->id, 'name' => $name, 'code' => $code]);

    return ProductVariant::create([
        'company_id' => $test->company->id, 'product_id' => $product->id,
        'sku' => $code.'-1', 'is_default' => true, 'purchase_price' => 1.0,
    ]);
}

function subAsmStock(object $test, ProductVariant $variant, float $qty): void
{
    Stock::create([
        'company_id' => $test->company->id, 'product_variant_id' => $variant->id,
        'warehouse_id' => $test->warehouse->id, 'quantity' => $qty, 'on_hold' => 0,
    ]);
}

function subAsmBom(object $test, ProductVariant $output, array $components): Bom
{
    $bom = Bom::create([
        'company_id' => $test->company->id, 'product_variant_id' => $output->id,
        'name' => $output->sku.' BOM', 'version' => '1', 'output_qty' => 1,
        'is_active' => true, 'is_default' => true,
    ]);
    foreach ($components as [$variant, $qty]) {
        BomItem::create([
            'bom_id' => $bom->id, 'product_variant_id' => $variant->id,
            'quantity' => $qty, 'item_type' => 'material', 'wastage_pct' => 0,
        ]);
    }

    return $bom;
}

beforeEach(function () {
    subAsmWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);
    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'SubAsm Co', 'code' => 'SAS',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);
    $this->user = User::create([
        'company_id' => $this->company->id, 'name' => 'SubAsm Tester',
        'email' => 'subasm-'.uniqid().'@example.com',
        'password' => bcrypt('password'), 'user_type' => UserTypeEnum::ADMIN,
    ]);
    $this->warehouse = Warehouse::create(['company_id' => $this->company->id, 'name' => 'Factory', 'code' => 'FACT']);

    // Raw materials
    $this->steel = subAsmVariant($this, 'Steel', 'STEEL');
    $this->rubber = subAsmVariant($this, 'Rubber', 'RUBBER');
    // Sub-assembly Wheel = 1 steel + 2 rubber (manufactured)
    $this->wheel = subAsmVariant($this, 'Wheel', 'WHEEL');
    subAsmBom($this, $this->wheel, [[$this->steel, 1], [$this->rubber, 2]]);
    // Bought-in component (no BOM)
    $this->bolt = subAsmVariant($this, 'Bolt', 'BOLT');
    // Finished good Cart = 4 wheels + 8 bolts
    $this->cart = subAsmVariant($this, 'Cart', 'CART');
    $this->cartBom = subAsmBom($this, $this->cart, [[$this->wheel, 4], [$this->bolt, 8]]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

it('creates draft sub-assembly orders for short manufactured components', function () {
    subAsmStock($this, $this->wheel, 2); // need 4×2 = 8, have 2 → short 6

    $response = $this->postJson("/api/admin/production-order/plan-subassemblies/{$this->cartBom->id}", [
        'planned_qty' => 2,
        'warehouse_id' => $this->warehouse->id,
    ]);

    $response->assertCreated();
    $orders = collect($response->json('data'));
    expect($orders)->toHaveCount(1);

    $wheelOrder = ProductionOrder::findOrFail($orders->first()['id']);
    expect($wheelOrder->bom->product_variant_id)->toBe($this->wheel->id);
    expect((float) $wheelOrder->planned_qty)->toBe(6.0);
    expect($wheelOrder->status)->toBe('draft');
});

it('does not reserve stock for the generated draft orders', function () {
    // No wheel stock at all → the order is still created (unreserved), no deadlock
    $response = $this->postJson("/api/admin/production-order/plan-subassemblies/{$this->cartBom->id}", [
        'planned_qty' => 1,
        'warehouse_id' => $this->warehouse->id,
    ]);

    $response->assertCreated();
    $orderId = collect($response->json('data'))->first()['id'];

    $openReservations = \Illuminate\Support\Facades\DB::table('stock_reservations')
        ->where('reservable_type', (new ProductionOrder)->getMorphClass())
        ->where('reservable_id', $orderId)
        ->whereNull('released_at')
        ->count();
    expect($openReservations)->toBe(0);
});

it('skips components that have enough stock', function () {
    subAsmStock($this, $this->wheel, 100); // plenty

    $response = $this->postJson("/api/admin/production-order/plan-subassemblies/{$this->cartBom->id}", [
        'planned_qty' => 2,
        'warehouse_id' => $this->warehouse->id,
    ]);

    $response->assertCreated();
    expect($response->json('data'))->toBe([]);
});

it('skips bought-in (non-manufactured) components', function () {
    // Bolts have no BOM and no stock; should NOT produce a sub-assembly order for them
    $response = $this->postJson("/api/admin/production-order/plan-subassemblies/{$this->cartBom->id}", [
        'planned_qty' => 2,
        'warehouse_id' => $this->warehouse->id,
    ]);

    $response->assertCreated();
    $outputIds = collect($response->json('data'))
        ->map(fn ($o) => ProductionOrder::find($o['id'])->bom->product_variant_id)
        ->all();
    expect($outputIds)->not->toContain($this->bolt->id);
});
