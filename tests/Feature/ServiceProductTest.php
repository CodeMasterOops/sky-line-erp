<?php

use App\Models\Unit;
use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Product;
use App\Enums\StatusEnum;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\StockMovement;
use App\Enums\ProductTypeEnum;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Models\ProductCategory;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function serviceProductWarmAllTablesCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $tables[$table] = Schema::getColumnListing($table);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

function serviceProductPayload(array $overrides = []): array
{
    return array_merge([
        'product_type' => ProductTypeEnum::SERVICE->value,
        'name' => 'Consulting',
        'code' => 'SVC-'.uniqid(),
        'product_category_id' => null,
        'unit_id' => null,
        'brand_id' => null,
        'hsn_code' => '9983',
        'has_variants' => false,
        'variants' => [
            [
                'sales_price' => 2500,
                'purchase_price' => 1200,
                'is_default' => true,
            ],
        ],
    ], $overrides);
}

function serviceProductSeedAccounts(Company $company): void
{
    $sales = Account::create([
        'company_id' => $company->id,
        'account_group_id' => null,
        'name' => 'Sales',
        'code' => 'SALES-SVC',
    ]);
    $customer = Account::create([
        'company_id' => $company->id,
        'account_group_id' => null,
        'name' => 'Customers',
        'code' => 'AR-SVC',
    ]);
    $purchase = Account::create([
        'company_id' => $company->id,
        'account_group_id' => null,
        'name' => 'Purchases',
        'code' => 'PUR-SVC',
    ]);
    $supplier = Account::create([
        'company_id' => $company->id,
        'account_group_id' => null,
        'name' => 'Suppliers',
        'code' => 'AP-SVC',
    ]);

    AccountSetting::create([
        'company_id' => $company->id,
        'sales_account_id' => $sales->id,
        'customer_account_id' => $customer->id,
        'purchase_account_id' => $purchase->id,
        'supplier_account_id' => $supplier->id,
    ]);
}

beforeEach(function () {
    serviceProductWarmAllTablesCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Service Product Test Co',
        'code' => 'SPTC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Service Tester',
        'email' => 'service-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Main',
        'code' => 'W1',
    ]);

    $this->category = ProductCategory::create([
        'company_id' => $this->company->id,
        'name' => 'Services',
    ]);

    $this->unit = Unit::create([
        'company_id' => $this->company->id,
        'name' => 'Hour',
        'code' => 'HR',
    ]);

    $this->physicalProduct = Product::create([
        'company_id' => $this->company->id,
        'product_category_id' => $this->category->id,
        'product_type' => ProductTypeEnum::PRODUCT,
        'name' => 'Widget',
        'code' => 'WIDGET-'.uniqid(),
        'unit_id' => $this->unit->id,
    ]);

    $this->physicalVariant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->physicalProduct->id,
        'sku' => 'SKU-PHYS-1',
        'sales_price' => 100,
        'purchase_price' => 50,
        'is_default' => true,
    ]);

    $this->customer = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Service Customer',
        'code' => 'CUST-SVC',
        'type' => PartyTypeEnum::CUSTOMER,
    ]);

    $this->supplier = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Service Supplier',
        'code' => 'SUP-SVC',
        'type' => PartyTypeEnum::SUPPLIER,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

it('defaults min_stock_level to zero when omitted on physical product create', function () {
    $response = $this->postJson('/api/admin/product', [
        'product_type' => ProductTypeEnum::PRODUCT->value,
        'name' => 'Test Product',
        'code' => 'PROD-'.uniqid(),
        'product_category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'min_stock_level' => '',
        'reorder_quantity' => 10,
        'has_variants' => false,
        'variants' => [
            [
                'sales_price' => 100,
                'purchase_price' => 50,
                'is_default' => true,
            ],
        ],
    ]);

    $response->assertCreated();
    $product = Product::query()->find($response->json('data.id'));
    expect($product)->not->toBeNull();
    expect((float) $product->min_stock_level)->toBe(0.0);
});

it('creates a service product without brand via API', function () {
    $response = $this->postJson('/api/admin/product', serviceProductPayload([
        'product_category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
    ]));

    $response->assertCreated();
    expect($response->json('data.product_type'))->toBe(ProductTypeEnum::SERVICE->value);
    expect($response->json('data.brand_id'))->toBe('');
    expect($response->json('data.hsn_code'))->toBe('9983');
});

it('creates a service without sku and optional cost', function () {
    $response = $this->postJson('/api/admin/product', serviceProductPayload([
        'product_category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'variants' => [
            [
                'sales_price' => 1500,
                'is_default' => true,
            ],
        ],
    ]));

    $response->assertCreated();
    $productId = $response->json('data.id');
    $variant = ProductVariant::query()->where('product_id', $productId)->first();
    expect($variant)->not->toBeNull();
    expect($variant->sku)->toBeNull();
    expect((float) $variant->purchase_price)->toBe(0.0);
});

it('rejects a service with more than one variant row', function () {
    $response = $this->postJson('/api/admin/product', serviceProductPayload([
        'product_category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'variants' => [
            ['sku' => 'A', 'sales_price' => 100, 'purchase_price' => 50, 'is_default' => true],
            ['sku' => 'B', 'sales_price' => 200, 'purchase_price' => 80, 'is_default' => false],
        ],
    ]));

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['variants']);
});

it('approves an invoice with a service line and no warehouse without stock movement', function () {
    serviceProductSeedAccounts($this->company);

    $serviceProduct = Product::create([
        'company_id' => $this->company->id,
        'product_category_id' => $this->category->id,
        'product_type' => ProductTypeEnum::SERVICE,
        'name' => 'Installation',
        'code' => 'INST-'.uniqid(),
        'unit_id' => $this->unit->id,
    ]);

    $serviceVariant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $serviceProduct->id,
        'sku' => 'INST-SKU',
        'sales_price' => 1500,
        'purchase_price' => 800,
        'is_default' => true,
    ]);

    $beforeMovements = StockMovement::withoutGlobalScopes()->count();

    $response = $this->postJson('/api/admin/invoice', [
        'invoice_date' => now()->toDateString(),
        'party_id' => $this->customer->id,
        'status' => StatusEnum::APPROVED->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'items' => [
            [
                'product_variant_id' => $serviceVariant->id,
                'warehouse_id' => null,
                'quantity' => 1,
                'rate' => 1500,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'tax_line_type' => 'taxable',
                'line_discount_type' => 'fixed',
                'line_discount_value' => 0,
            ],
        ],
    ]);

    $response->assertCreated();
    expect(StockMovement::withoutGlobalScopes()->count())->toBe($beforeMovements);
    expect(DB::table('invoice_items')->value('warehouse_id'))->toBeNull();
});

it('requires warehouse for physical product invoice lines', function () {
    $serviceProduct = Product::create([
        'company_id' => $this->company->id,
        'product_category_id' => $this->category->id,
        'product_type' => ProductTypeEnum::PRODUCT,
        'name' => 'Physical',
        'code' => 'PHYS-'.uniqid(),
        'unit_id' => $this->unit->id,
    ]);

    $physicalVariant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $serviceProduct->id,
        'sku' => 'PHYS-SKU',
        'sales_price' => 100,
        'purchase_price' => 50,
        'is_default' => true,
    ]);

    $response = $this->postJson('/api/admin/invoice', [
        'invoice_date' => now()->toDateString(),
        'party_id' => $this->customer->id,
        'status' => StatusEnum::DRAFT->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'items' => [
            [
                'product_variant_id' => $physicalVariant->id,
                'warehouse_id' => null,
                'quantity' => 1,
                'rate' => 100,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'tax_line_type' => 'taxable',
                'line_discount_type' => 'fixed',
                'line_discount_value' => 0,
            ],
        ],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['items.0.warehouse_id']);
});

it('rejects a purchase bill with a service line', function () {
    serviceProductSeedAccounts($this->company);

    $serviceProduct = Product::create([
        'company_id' => $this->company->id,
        'product_category_id' => $this->category->id,
        'product_type' => ProductTypeEnum::SERVICE,
        'name' => 'Subcontract Labor',
        'code' => 'SUB-'.uniqid(),
        'unit_id' => $this->unit->id,
    ]);

    $serviceVariant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $serviceProduct->id,
        'sales_price' => 0,
        'purchase_price' => 500,
        'is_default' => true,
    ]);

    $response = $this->postJson('/api/admin/bill', [
        'bill_date' => now()->toDateString(),
        'party_id' => $this->supplier->id,
        'status' => StatusEnum::APPROVED->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'items' => [
            [
                'product_variant_id' => $serviceVariant->id,
                'warehouse_id' => null,
                'quantity' => 2,
                'rate' => 500,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'tax_line_type' => 'taxable',
                'line_discount_type' => 'fixed',
                'line_discount_value' => 0,
            ],
        ],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['items.0.product_variant_id']);
});

it('returns product_type and is_service from variant search', function () {
    $serviceProduct = Product::create([
        'company_id' => $this->company->id,
        'product_category_id' => $this->category->id,
        'product_type' => ProductTypeEnum::SERVICE,
        'name' => 'Support Plan',
        'code' => 'SUP-'.uniqid(),
        'unit_id' => $this->unit->id,
    ]);

    ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $serviceProduct->id,
        'sku' => 'SUP-SKU',
        'sales_price' => 3000,
        'purchase_price' => 0,
        'is_default' => true,
    ]);

    $response = $this->getJson('/api/admin/product/variant/search?q=Support');

    $response->assertSuccessful();
    $row = collect($response->json('data'))->first();
    expect($row['product_type'])->toBe(ProductTypeEnum::SERVICE->value);
    expect($row['is_service'])->toBeTrue();
});

it('exposes null total_stock for services in product list', function () {
    Product::create([
        'company_id' => $this->company->id,
        'product_category_id' => $this->category->id,
        'product_type' => ProductTypeEnum::SERVICE,
        'name' => 'Audit Service',
        'code' => 'AUD-'.uniqid(),
        'unit_id' => $this->unit->id,
    ]);

    $response = $this->getJson('/api/admin/product?product_type=service');

    $response->assertSuccessful();
    expect($response->json('data.0.total_stock'))->toBeNull();
});
