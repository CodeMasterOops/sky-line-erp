<?php

use App\Models\Bom;
use App\Models\User;
use App\Models\BomItem;
use App\Models\Company;
use App\Models\Product;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function explosionWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
}

function makeVariant(object $test, string $name, string $code): ProductVariant
{
    $product = Product::create(['company_id' => $test->company->id, 'name' => $name, 'code' => $code]);

    return ProductVariant::create([
        'company_id' => $test->company->id, 'product_id' => $product->id,
        'sku' => $code.'-1', 'is_default' => true, 'purchase_price' => 1.0,
    ]);
}

beforeEach(function () {
    explosionWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);
    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Explosion Co', 'code' => 'EXP',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);
    $this->user = User::create([
        'company_id' => $this->company->id, 'name' => 'Exp Tester',
        'email' => 'exp-'.uniqid().'@example.com',
        'password' => bcrypt('password'), 'user_type' => UserTypeEnum::ADMIN,
    ]);

    // Leaf raw materials
    $this->steel = makeVariant($this, 'Steel', 'STEEL');
    $this->rubber = makeVariant($this, 'Rubber', 'RUBBER');

    // Sub-assembly: Wheel = 1 steel + 2 rubber
    $this->wheel = makeVariant($this, 'Wheel', 'WHEEL');
    $this->wheelBom = Bom::create([
        'company_id' => $this->company->id, 'product_variant_id' => $this->wheel->id,
        'name' => 'Wheel BOM', 'version' => '1', 'output_qty' => 1, 'is_active' => true, 'is_default' => true,
    ]);
    BomItem::create(['bom_id' => $this->wheelBom->id, 'product_variant_id' => $this->steel->id, 'quantity' => 1, 'item_type' => 'material']);
    BomItem::create(['bom_id' => $this->wheelBom->id, 'product_variant_id' => $this->rubber->id, 'quantity' => 2, 'item_type' => 'material']);

    // Finished good: Cart = 4 wheels + 5 steel (direct)
    $this->cart = makeVariant($this, 'Cart', 'CART');
    $this->cartBom = Bom::create([
        'company_id' => $this->company->id, 'product_variant_id' => $this->cart->id,
        'name' => 'Cart BOM', 'version' => '1', 'output_qty' => 1, 'is_active' => true, 'is_default' => true,
    ]);
    BomItem::create(['bom_id' => $this->cartBom->id, 'product_variant_id' => $this->wheel->id, 'quantity' => 4, 'item_type' => 'material']);
    BomItem::create(['bom_id' => $this->cartBom->id, 'product_variant_id' => $this->steel->id, 'quantity' => 5, 'item_type' => 'material']);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

it('explodes a multi-level BOM into a component tree', function () {
    $response = $this->getJson("/api/admin/bom/{$this->cartBom->id}/explode?qty=1");
    $response->assertSuccessful();

    $tree = $response->json('data.tree');
    expect($tree)->toHaveCount(2);

    $wheelNode = collect($tree)->firstWhere('variant_id', $this->wheel->id);
    expect($wheelNode['is_sub_assembly'])->toBeTrue();
    expect((float) $wheelNode['qty'])->toBe(4.0);
    expect($wheelNode['children'])->toHaveCount(2); // steel + rubber
});

it('aggregates leaf material requirements across levels', function () {
    // Build 2 carts: wheels = 4×2 = 8 → steel from wheels 8×1 = 8, rubber 8×2 = 16
    // plus direct steel 5×2 = 10 → steel total 18, rubber 16
    $response = $this->getJson("/api/admin/bom/{$this->cartBom->id}/explode?qty=2");
    $response->assertSuccessful();

    $materials = collect($response->json('data.materials'));
    $steel = $materials->firstWhere('variant_id', $this->steel->id);
    $rubber = $materials->firstWhere('variant_id', $this->rubber->id);

    expect((float) $steel['total_qty'])->toBe(18.0);
    expect((float) $rubber['total_qty'])->toBe(16.0);
    // Wheel is a sub-assembly, not a leaf material
    expect($materials->firstWhere('variant_id', $this->wheel->id))->toBeNull();
});

it('lists BOMs that consume a given variant (where-used)', function () {
    // Steel is used by both the Wheel BOM and the Cart BOM
    $response = $this->getJson("/api/admin/bom/where-used/{$this->steel->id}");
    $response->assertSuccessful();

    $bomIds = collect($response->json('data'))->pluck('bom_id')->sort()->values()->all();
    expect($bomIds)->toBe([$this->wheelBom->id, $this->cartBom->id]);
});

it('returns an empty where-used list for a variant not used in any BOM', function () {
    $unused = makeVariant($this, 'Unused', 'UNUSED');

    $response = $this->getJson("/api/admin/bom/where-used/{$unused->id}");
    $response->assertSuccessful();
    expect($response->json('data'))->toBe([]);
});
