<?php

use App\Models\Unit;
use App\Models\User;
use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Enums\ItemRoleEnum;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Enums\ProductTypeEnum;
use App\Models\ProductVariant;
use App\Models\ProductCategory;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function itemRoleWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
}

beforeEach(function () {
    itemRoleWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Role Test Co',
        'code' => 'ROLE',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Role Tester',
        'email' => 'role-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    Warehouse::create(['company_id' => $this->company->id, 'name' => 'Main', 'code' => 'W1']);

    $this->category = ProductCategory::create(['company_id' => $this->company->id, 'name' => 'Materials']);
    $this->unit = Unit::create(['company_id' => $this->company->id, 'name' => 'Kg', 'code' => 'KG']);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

function productPayload(object $test, array $overrides = []): array
{
    return array_merge([
        'product_type' => ProductTypeEnum::PRODUCT->value,
        'name' => 'Flour '.uniqid(),
        'code' => 'PROD-'.uniqid(),
        'product_category_id' => $test->category->id,
        'unit_id' => $test->unit->id,
        'has_variants' => false,
        'variants' => [
            ['sales_price' => 100, 'purchase_price' => 50, 'is_default' => true],
        ],
    ], $overrides);
}

it('persists item_role on a physical product and returns it in the resource', function () {
    $response = $this->postJson('/api/admin/product', productPayload($this, [
        'item_role' => ItemRoleEnum::RawMaterial->value,
    ]));

    $response->assertCreated();
    expect($response->json('data.item_role'))->toBe('raw_material');
    expect($response->json('data.item_role_label'))->toBe('Raw Material');

    $product = Product::find($response->json('data.id'));
    expect($product->item_role)->toBe(ItemRoleEnum::RawMaterial);
});

it('treats item_role as optional', function () {
    $response = $this->postJson('/api/admin/product', productPayload($this));

    $response->assertCreated();
    expect($response->json('data.item_role'))->toBe('');
    expect(Product::find($response->json('data.id'))->item_role)->toBeNull();
});

it('updates the item_role of an existing product', function () {
    $id = $this->postJson('/api/admin/product', productPayload($this, [
        'item_role' => ItemRoleEnum::RawMaterial->value,
    ]))->json('data.id');

    $this->putJson("/api/admin/product/{$id}", productPayload($this, [
        'code' => 'PROD-UPD-'.uniqid(),
        'item_role' => ItemRoleEnum::SemiFinished->value,
    ]))->assertOk();

    expect(Product::find($id)->item_role)->toBe(ItemRoleEnum::SemiFinished);
});

it('strips item_role from a service product', function () {
    $response = $this->postJson('/api/admin/product', [
        'product_type' => ProductTypeEnum::SERVICE->value,
        'name' => 'Consulting '.uniqid(),
        'code' => 'SVC-'.uniqid(),
        'product_category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'item_role' => ItemRoleEnum::RawMaterial->value,
        'has_variants' => false,
        'variants' => [['sales_price' => 100, 'is_default' => true]],
    ]);

    $response->assertCreated();
    expect($response->json('data.item_role'))->toBe('');
    expect(Product::find($response->json('data.id'))->item_role)->toBeNull();
});

it('rejects an invalid item_role value', function () {
    $response = $this->postJson('/api/admin/product', productPayload($this, [
        'item_role' => 'not_a_role',
    ]));

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['item_role']);
});

it('filters the product list by item_role', function () {
    $this->postJson('/api/admin/product', productPayload($this, [
        'name' => 'Steel', 'item_role' => ItemRoleEnum::RawMaterial->value,
    ]))->assertCreated();
    $this->postJson('/api/admin/product', productPayload($this, [
        'name' => 'Table', 'item_role' => ItemRoleEnum::FinishedGood->value,
    ]))->assertCreated();

    $response = $this->getJson('/api/admin/product?item_role=raw_material');

    $response->assertSuccessful();
    $roles = collect($response->json('data'))->pluck('item_role')->unique()->values()->all();
    expect($roles)->toBe(['raw_material']);
});

function createVariantWithRole(object $test, ?ItemRoleEnum $role, string $sku): ProductVariant
{
    $product = Product::create([
        'company_id' => $test->company->id,
        'product_category_id' => $test->category->id,
        'product_type' => ProductTypeEnum::PRODUCT,
        'item_role' => $role,
        'name' => 'Roled '.$sku,
        'code' => 'CODE-'.$sku,
        'unit_id' => $test->unit->id,
    ]);

    return ProductVariant::create([
        'company_id' => $test->company->id,
        'product_id' => $product->id,
        'sku' => $sku,
        'sales_price' => 10,
        'purchase_price' => 8,
        'is_default' => true,
    ]);
}

function createVariantWithFlags(object $test, bool $saleable, bool $purchasable, string $sku): ProductVariant
{
    $product = Product::create([
        'company_id' => $test->company->id,
        'product_category_id' => $test->category->id,
        'product_type' => ProductTypeEnum::PRODUCT,
        'is_saleable' => $saleable,
        'is_purchasable' => $purchasable,
        'name' => 'Flagged '.$sku,
        'code' => 'FLAG-'.$sku,
        'unit_id' => $test->unit->id,
    ]);

    return ProductVariant::create([
        'company_id' => $test->company->id,
        'product_id' => $product->id,
        'sku' => $sku,
        'sales_price' => 10,
        'purchase_price' => 8,
        'is_default' => true,
    ]);
}

it('defaults is_saleable and is_purchasable to true', function () {
    $response = $this->postJson('/api/admin/product', productPayload($this));

    $response->assertCreated();
    expect($response->json('data.is_saleable'))->toBeTrue();
    expect($response->json('data.is_purchasable'))->toBeTrue();
});

it('persists is_saleable and is_purchasable flags', function () {
    $response = $this->postJson('/api/admin/product', productPayload($this, [
        'item_role' => ItemRoleEnum::RawMaterial->value,
        'is_saleable' => false,
        'is_purchasable' => true,
    ]));

    $response->assertCreated();
    $product = Product::find($response->json('data.id'));
    expect($product->is_saleable)->toBeFalse();
    expect($product->is_purchasable)->toBeTrue();
});

it('hides non-saleable products from the saleable variant search', function () {
    createVariantWithFlags($this, saleable: true, purchasable: true, sku: 'SALE-YES');
    createVariantWithFlags($this, saleable: false, purchasable: true, sku: 'SALE-NO');

    $response = $this->getJson('/api/admin/product/variant/search?saleable_only=1');

    $response->assertSuccessful();
    $skus = collect($response->json('data'))->pluck('sku')->all();
    expect($skus)->toContain('SALE-YES');
    expect($skus)->not->toContain('SALE-NO');
});

it('hides non-purchasable products from the purchasable variant search', function () {
    createVariantWithFlags($this, saleable: true, purchasable: true, sku: 'BUY-YES');
    createVariantWithFlags($this, saleable: true, purchasable: false, sku: 'BUY-NO');

    $response = $this->getJson('/api/admin/product/variant/search?purchasable_only=1');

    $response->assertSuccessful();
    $skus = collect($response->json('data'))->pluck('sku')->all();
    expect($skus)->toContain('BUY-YES');
    expect($skus)->not->toContain('BUY-NO');
});

it('returns all products when no availability filter is applied', function () {
    createVariantWithFlags($this, saleable: false, purchasable: false, sku: 'NONE');

    $response = $this->getJson('/api/admin/product/variant/search');

    $response->assertSuccessful();
    $skus = collect($response->json('data'))->pluck('sku')->all();
    expect($skus)->toContain('NONE');
});

it('exposes availability flags in the variant search picker', function () {
    createVariantWithFlags($this, saleable: false, purchasable: true, sku: 'FLAGS-CHK');

    $response = $this->getJson('/api/admin/product/variant/search?q=FLAGS');

    $response->assertSuccessful();
    $row = collect($response->json('data'))->firstWhere('sku', 'FLAGS-CHK');
    expect($row['is_saleable'])->toBeFalse();
    expect($row['is_purchasable'])->toBeTrue();
});

it('filters the variant search by item_roles', function () {
    createVariantWithRole($this, ItemRoleEnum::RawMaterial, 'RAW-SKU');
    createVariantWithRole($this, ItemRoleEnum::FinishedGood, 'FIN-SKU');
    createVariantWithRole($this, ItemRoleEnum::Consumable, 'CON-SKU');

    $response = $this->getJson('/api/admin/product/variant/search?item_roles=raw_material,consumable');

    $response->assertSuccessful();
    $skus = collect($response->json('data'))->pluck('sku')->all();
    expect($skus)->toContain('RAW-SKU', 'CON-SKU');
    expect($skus)->not->toContain('FIN-SKU');
});

it('returns all roles when item_roles is omitted', function () {
    createVariantWithRole($this, ItemRoleEnum::RawMaterial, 'RAW-2');
    createVariantWithRole($this, ItemRoleEnum::FinishedGood, 'FIN-2');

    $response = $this->getJson('/api/admin/product/variant/search');

    $response->assertSuccessful();
    $skus = collect($response->json('data'))->pluck('sku')->all();
    expect($skus)->toContain('RAW-2', 'FIN-2');
});

it('ignores invalid item_roles values in the variant search', function () {
    createVariantWithRole($this, ItemRoleEnum::RawMaterial, 'RAW-3');

    $response = $this->getJson('/api/admin/product/variant/search?item_roles=not_a_role');

    $response->assertSuccessful();
    $skus = collect($response->json('data'))->pluck('sku')->all();
    expect($skus)->toContain('RAW-3');
});

it('exposes item_role_label in the variant search picker', function () {
    $product = Product::create([
        'company_id' => $this->company->id,
        'product_category_id' => $this->category->id,
        'product_type' => ProductTypeEnum::PRODUCT,
        'item_role' => ItemRoleEnum::RawMaterial,
        'name' => 'Searchable Steel',
        'code' => 'STEEL-'.uniqid(),
        'unit_id' => $this->unit->id,
    ]);
    ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'sku' => 'STEEL-SKU',
        'sales_price' => 10,
        'purchase_price' => 8,
        'is_default' => true,
    ]);

    $response = $this->getJson('/api/admin/product/variant/search?q=Searchable');

    $response->assertSuccessful();
    $row = collect($response->json('data'))->firstWhere('sku', 'STEEL-SKU');
    expect($row['item_role'])->toBe('raw_material');
    expect($row['item_role_label'])->toBe('Raw Material');
});
