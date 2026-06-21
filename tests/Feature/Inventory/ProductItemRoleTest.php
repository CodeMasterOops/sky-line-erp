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
