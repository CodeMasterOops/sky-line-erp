<?php

use App\Models\Unit;
use App\Models\User;
use App\Models\Company;
use App\Models\Product;
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

function productBarcodeWarmAllTablesCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $tables[$table] = Schema::getColumnListing($table);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

function productBarcodePayload(array $overrides = []): array
{
    return array_merge([
        'product_type' => ProductTypeEnum::PRODUCT->value,
        'name' => 'Barcode Widget',
        'code' => 'BC-'.uniqid(),
        'product_category_id' => null,
        'unit_id' => null,
        'reorder_quantity' => 5,
        'has_variants' => false,
        'variants' => [
            [
                'sku' => 'SKU-'.uniqid(),
                'barcode' => '890'.random_int(1000000000, 9999999999),
                'sales_price' => 150,
                'purchase_price' => 75,
                'is_default' => true,
            ],
        ],
    ], $overrides);
}

beforeEach(function () {
    productBarcodeWarmAllTablesCache();

    $this->company = Company::create([
        'company_name' => 'Barcode Test Co',
        'code' => 'BTC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Barcode Tester',
        'email' => 'barcode-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->category = ProductCategory::create([
        'company_id' => $this->company->id,
        'name' => 'General',
    ]);

    $this->unit = Unit::create([
        'company_id' => $this->company->id,
        'name' => 'Piece',
        'code' => 'PC',
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

it('stores product with separate sku and barcode on variant', function () {
    $payload = productBarcodePayload([
        'product_category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
    ]);

    $response = $this->postJson('/api/admin/product', $payload);

    $response->assertCreated();

    $variant = ProductVariant::query()->where('product_id', $response->json('data.id'))->first();

    expect($variant)->not->toBeNull();
    expect($variant->sku)->toBe($payload['variants'][0]['sku']);
    expect($variant->barcode)->toBe($payload['variants'][0]['barcode']);
    expect($variant->sku)->not->toBe($variant->barcode);
});

it('updates product variant barcode', function () {
    $payload = productBarcodePayload([
        'product_category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
    ]);

    $create = $this->postJson('/api/admin/product', $payload)->assertCreated();

    $productId = $create->json('data.id');
    $variant = ProductVariant::query()->where('product_id', $productId)->firstOrFail();
    $newBarcode = '8909999999999';

    $response = $this->putJson("/api/admin/product/{$productId}", [
        'product_type' => ProductTypeEnum::PRODUCT->value,
        'name' => 'Barcode Widget Updated',
        'code' => $create->json('data.code'),
        'product_category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'reorder_quantity' => 5,
        'has_variants' => false,
        'variants' => [
            [
                'id' => $variant->id,
                'sku' => 'SKU-UPDATED',
                'barcode' => $newBarcode,
                'sales_price' => 160,
                'purchase_price' => 80,
                'is_default' => true,
            ],
        ],
    ]);

    $response->assertSuccessful();

    $variant->refresh();
    expect($variant->barcode)->toBe($newBarcode);
    expect($variant->sku)->toBe('SKU-UPDATED');

    $show = $this->getJson("/api/admin/product/{$productId}")->assertSuccessful();
    expect($show->json('data.variants.0.barcode'))->toBe($newBarcode);
    expect($show->json('data.variants.0.sku'))->toBe('SKU-UPDATED');
});

it('finds variant by dedicated barcode field when scanning', function () {
    $barcode = '8901234567890';
    $product = Product::create([
        'company_id' => $this->company->id,
        'product_category_id' => $this->category->id,
        'product_type' => ProductTypeEnum::PRODUCT,
        'name' => 'Scannable Item',
        'code' => 'SCAN-'.uniqid(),
        'unit_id' => $this->unit->id,
    ]);

    ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'sku' => 'INTERNAL-SKU-ONLY',
        'barcode' => $barcode,
        'sales_price' => 100,
        'purchase_price' => 50,
        'is_default' => true,
    ]);

    $response = $this->getJson('/api/admin/product/variant/search?barcode='.$barcode);

    $response->assertSuccessful();
    expect($response->json('data.0.barcode'))->toBe($barcode);
    expect($response->json('data.0.sku'))->toBe('INTERNAL-SKU-ONLY');
});

it('still finds variant by sku when barcode is not set', function () {
    $sku = 'LEGACY-SKU-'.uniqid();
    $product = Product::create([
        'company_id' => $this->company->id,
        'product_category_id' => $this->category->id,
        'product_type' => ProductTypeEnum::PRODUCT,
        'name' => 'Legacy Item',
        'code' => 'LEG-'.uniqid(),
        'unit_id' => $this->unit->id,
    ]);

    ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'sku' => $sku,
        'barcode' => null,
        'sales_price' => 100,
        'purchase_price' => 50,
        'is_default' => true,
    ]);

    $response = $this->getJson('/api/admin/product/variant/search?barcode='.$sku);

    $response->assertSuccessful();
    expect($response->json('data.0.sku'))->toBe($sku);
});

it('rejects duplicate barcode within the same company', function () {
    $barcode = '8901111111111';

    $this->postJson('/api/admin/product', productBarcodePayload([
        'product_category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'variants' => [
            [
                'sku' => 'SKU-A',
                'barcode' => $barcode,
                'sales_price' => 100,
                'purchase_price' => 50,
                'is_default' => true,
            ],
        ],
    ]))->assertCreated();

    $response = $this->postJson('/api/admin/product', productBarcodePayload([
        'product_category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'variants' => [
            [
                'sku' => 'SKU-B',
                'barcode' => $barcode,
                'sales_price' => 120,
                'purchase_price' => 60,
                'is_default' => true,
            ],
        ],
    ]));

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['variants.0.barcode']);
});

it('forces barcode to null for service products', function () {
    $response = $this->postJson('/api/admin/product', [
        'product_type' => ProductTypeEnum::SERVICE->value,
        'name' => 'Consulting',
        'code' => 'SVC-'.uniqid(),
        'product_category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'has_variants' => false,
        'variants' => [
            [
                'sku' => 'SHOULD-BE-IGNORED',
                'barcode' => '8902222222222',
                'sales_price' => 2500,
                'purchase_price' => 0,
                'is_default' => true,
            ],
        ],
    ]);

    $response->assertCreated();

    $variant = ProductVariant::query()->where('product_id', $response->json('data.id'))->first();
    expect($variant->sku)->toBeNull();
    expect($variant->barcode)->toBeNull();
});

it('includes barcode in text search results', function () {
    $barcode = '8903333333333';
    $product = Product::create([
        'company_id' => $this->company->id,
        'product_category_id' => $this->category->id,
        'product_type' => ProductTypeEnum::PRODUCT,
        'name' => 'Searchable Barcode Product',
        'code' => 'SBP-'.uniqid(),
        'unit_id' => $this->unit->id,
    ]);

    ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'sku' => 'SKU-SEARCH',
        'barcode' => $barcode,
        'sales_price' => 100,
        'purchase_price' => 50,
        'is_default' => true,
    ]);

    $response = $this->getJson('/api/admin/product/variant/search?q='.$barcode);

    $response->assertSuccessful();
    expect($response->json('data.0.barcode'))->toBe($barcode);
});
