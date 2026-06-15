<?php

use App\Models\Unit;
use App\Models\User;
use App\Models\Company;
use App\Models\Product;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Enums\ProductTypeEnum;
use App\Models\ProductCategory;
use App\Services\TenantService;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $fiscalYear->id,
        'company_name' => 'Category Co',
        'code' => 'CAT',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Category Admin',
        'email' => 'cat-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

it('creates a root category and subcategory', function () {
    $rootResponse = $this->postJson('/api/admin/product-category', [
        'name' => 'Electronics',
        'description' => 'Devices',
    ]);

    $rootResponse->assertCreated();
    $rootId = $rootResponse->json('data.id');

    $childResponse = $this->postJson('/api/admin/product-category', [
        'parent_id' => $rootId,
        'name' => 'Mobile Phones',
        'description' => 'Handsets',
    ]);

    $childResponse->assertCreated()
        ->assertJsonPath('data.parent_id', $rootId)
        ->assertJsonPath('data.full_path', 'Electronics > Mobile Phones')
        ->assertJsonPath('data.is_leaf', true);

    expect(ProductCategory::query()->whereKey($childId = $childResponse->json('data.id'))->value('parent_id'))
        ->toBe((int) $rootId);
});

it('rejects circular parent assignment on update', function () {
    $root = ProductCategory::create([
        'company_id' => $this->company->id,
        'name' => 'Root',
    ]);

    $child = ProductCategory::create([
        'company_id' => $this->company->id,
        'parent_id' => $root->id,
        'name' => 'Child',
    ]);

    $response = $this->putJson("/api/admin/product-category/{$root->id}", [
        'parent_id' => $child->id,
        'name' => 'Root',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_id']);
});

it('blocks delete when subcategories exist', function () {
    $root = ProductCategory::create([
        'company_id' => $this->company->id,
        'name' => 'Root',
    ]);

    ProductCategory::create([
        'company_id' => $this->company->id,
        'parent_id' => $root->id,
        'name' => 'Child',
    ]);

    $response = $this->deleteJson("/api/admin/product-category/{$root->id}");

    $response->assertStatus(422)
        ->assertJsonPath('message', __('Cannot delete a category that has subcategories. Remove or reassign them first.'));
});

it('blocks delete when products are assigned', function () {
    $category = ProductCategory::create([
        'company_id' => $this->company->id,
        'name' => 'Assigned',
    ]);

    $unit = Unit::create([
        'company_id' => $this->company->id,
        'name' => 'Piece',
        'code' => 'PCS',
    ]);

    Product::create([
        'company_id' => $this->company->id,
        'product_category_id' => $category->id,
        'product_type' => ProductTypeEnum::PRODUCT,
        'name' => 'Sample',
        'code' => 'SAMPLE-1',
        'unit_id' => $unit->id,
    ]);

    $response = $this->deleteJson("/api/admin/product-category/{$category->id}");

    $response->assertStatus(422)
        ->assertJsonPath('message', __('Cannot delete a category assigned to products.'));
});

it('allows the same name under different parents', function () {
    $electronics = ProductCategory::create([
        'company_id' => $this->company->id,
        'name' => 'Electronics',
    ]);

    $clothing = ProductCategory::create([
        'company_id' => $this->company->id,
        'name' => 'Clothing',
    ]);

    ProductCategory::create([
        'company_id' => $this->company->id,
        'parent_id' => $electronics->id,
        'name' => 'Accessories',
    ]);

    ProductCategory::create([
        'company_id' => $this->company->id,
        'parent_id' => $clothing->id,
        'name' => 'Accessories',
    ]);

    expect(ProductCategory::query()->where('name', 'Accessories')->count())->toBe(2);
});

it('rejects assigning a parent category to a product', function () {
    $root = ProductCategory::create([
        'company_id' => $this->company->id,
        'name' => 'Electronics',
    ]);

    ProductCategory::create([
        'company_id' => $this->company->id,
        'parent_id' => $root->id,
        'name' => 'Phones',
    ]);

    $unit = Unit::create([
        'company_id' => $this->company->id,
        'name' => 'Piece',
        'code' => 'PCS',
    ]);

    $response = $this->postJson('/api/admin/product', [
        'product_type' => ProductTypeEnum::PRODUCT->value,
        'name' => 'Invalid Category Product',
        'code' => 'INV-'.uniqid(),
        'product_category_id' => $root->id,
        'unit_id' => $unit->id,
        'has_variants' => false,
        'variants' => [
            [
                'sales_price' => 100,
                'purchase_price' => 50,
                'is_default' => true,
            ],
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['product_category_id']);
});

it('returns pos categories endpoint', function () {
    $root = ProductCategory::create([
        'company_id' => $this->company->id,
        'name' => 'Electronics',
    ]);

    ProductCategory::create([
        'company_id' => $this->company->id,
        'parent_id' => $root->id,
        'name' => 'Phones',
    ]);

    $response = $this->getJson('/api/admin/pos/categories');

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['full_path' => 'Electronics > Phones', 'is_leaf' => true]);
});
