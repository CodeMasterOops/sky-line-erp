<?php

use App\Models\Tax;
use App\Models\Bill;
use App\Models\User;
use App\Models\Company;
use App\Models\Product;
use App\Models\BillItem;
use App\Enums\StatusEnum;
use App\Models\Warehouse;
use App\Enums\TaxTypeEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Enums\ChangeTypeEnum;
use App\Enums\TdsCategoryEnum;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use App\Services\Inventory\InventoryCostCalculator;
use App\Services\Inventory\InventoryLayerReceiptService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function invoiceWarmAllTablesCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $tables[$table] = Schema::getColumnListing($table);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

function invoiceSeedVariantStock(object $test, int $warehouseId, int $quantity, ?ProductVariant $variant = null): void
{
    invoiceWarmAllTablesCache();

    $variant ??= $test->variant;

    $bill = Bill::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'bill_no' => 'BILL-INV-'.uniqid(),
        'bill_date' => now()->toDateString(),
        'create_user_id' => $test->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $item = BillItem::create([
        'bill_id' => $bill->id,
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouseId,
        'quantity' => $quantity,
        'rate' => 50,
        'discount_amount' => 0,
    ]);

    $receipt = app(InventoryLayerReceiptService::class);

    DB::transaction(function () use ($receipt, $test, $bill, $item, $warehouseId, $quantity, $variant) {
        $receipt->receive(
            $test->company,
            $bill,
            $variant->id,
            $warehouseId,
            $quantity,
            InventoryCostCalculator::unitCostFromBillItem($item),
            ChangeTypeEnum::PURCHASE,
            $test->user->id,
            null,
            $item->id,
        );
    });
}

function invoiceStorePayload(object $test, array $overrides = []): array
{
    return array_merge([
        'invoice_date' => now()->toDateString(),
        'status' => StatusEnum::DRAFT->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'items' => [
            [
                'product_variant_id' => $test->variant->id,
                'warehouse_id' => $test->warehouse->id,
                'quantity' => 1,
                'rate' => 100,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'tax_line_type' => 'taxable',
                'line_discount_type' => 'fixed',
                'line_discount_value' => 0,
            ],
        ],
    ], $overrides);
}

beforeEach(function () {
    invoiceWarmAllTablesCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Invoice Test Co',
        'code' => 'ITC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Invoice Tester',
        'email' => 'invoice-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Main',
        'code' => 'W1',
    ]);

    $this->warehouseB = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Branch',
        'code' => 'W2',
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Widget',
        'code' => 'WIDGET',
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'SKU-INV-1',
        'sales_price' => 100,
        'is_default' => true,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');

    TenantService::setCompanyId($this->company->id);
});

it('stores invoice lines with per-line warehouse ids including same variant in two warehouses', function () {
    invoiceSeedVariantStock($this, $this->warehouse->id, 5);
    invoiceSeedVariantStock($this, $this->warehouseB->id, 3);

    $response = $this->postJson('/api/admin/invoice', invoiceStorePayload($this, [
        'items' => [
            [
                'product_variant_id' => $this->variant->id,
                'warehouse_id' => $this->warehouse->id,
                'quantity' => 1,
                'rate' => 100,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'tax_line_type' => 'taxable',
                'line_discount_type' => 'fixed',
                'line_discount_value' => 0,
            ],
            [
                'product_variant_id' => $this->variant->id,
                'warehouse_id' => $this->warehouseB->id,
                'quantity' => 2,
                'rate' => 100,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'tax_line_type' => 'taxable',
                'line_discount_type' => 'fixed',
                'line_discount_value' => 0,
            ],
        ],
    ]));

    $response->assertCreated();

    expect(DB::table('invoice_items')->count())->toBe(2);
    expect(DB::table('invoice_items')->pluck('warehouse_id')->sort()->values()->all())
        ->toBe([$this->warehouse->id, $this->warehouseB->id]);
});

it('rejects tds taxes on invoice line items', function () {
    invoiceSeedVariantStock($this, $this->warehouse->id, 5);

    $tdsTax = Tax::create([
        'company_id' => $this->company->id,
        'name' => 'TDS 1.5%',
        'rate' => 1.5,
        'type' => TaxTypeEnum::TDS,
        'tds_category' => TdsCategoryEnum::CONTRACT_VAT_REGISTERED,
        'is_system' => false,
    ]);

    $response = $this->postJson('/api/admin/invoice', invoiceStorePayload($this, [
        'items' => [
            [
                'product_variant_id' => $this->variant->id,
                'warehouse_id' => $this->warehouse->id,
                'quantity' => 1,
                'rate' => 100,
                'tax_id' => $tdsTax->id,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'tax_line_type' => 'taxable',
                'line_discount_type' => 'fixed',
                'line_discount_value' => 0,
            ],
        ],
    ]));

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['items.0.tax_id']);
});
