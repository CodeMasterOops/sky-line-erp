<?php

use App\Models\Bill;
use App\Models\User;
use App\Models\Party;
use App\Models\Stock;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\BillItem;
use App\Enums\StatusEnum;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use App\Models\PosHeldOrder;
use Laravel\Sanctum\Sanctum;
use App\Enums\ChangeTypeEnum;
use App\Enums\ProductTypeEnum;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use App\Services\Inventory\InventoryCostCalculator;
use App\Services\Inventory\InventoryLayerReceiptService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function warmAllTablesCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $tables[$table] = Schema::getColumnListing($table);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

beforeEach(function () {
    warmAllTablesCache();
    TenantService::setCompanyId(null);
    TenantService::setBranchId(null);

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
        'name' => 'POS Tester',
        'email' => 'pos-'.uniqid().'@example.com',
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
        'sku' => 'SKU-POS-1',
        'sales_price' => 100,
        'is_default' => true,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
});

function seedVariantStock(object $test, int $warehouseId, int $quantity, ?ProductVariant $variant = null): void
{
    warmAllTablesCache();

    $variant ??= $test->variant;

    $bill = Bill::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'bill_no' => 'BILL-POS-'.uniqid(),
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

function posCheckoutPayload(object $test, array $overrides = []): array
{
    return array_merge([
        'payment_method' => 'cash',
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
                'line_discount_type' => 'fixed',
                'line_discount_value' => 0,
            ],
        ],
    ], $overrides);
}

it('rejects checkout with a service line item', function () {
    $serviceProduct = Product::create([
        'company_id' => $this->company->id,
        'product_type' => ProductTypeEnum::SERVICE,
        'name' => 'Consulting',
        'code' => 'SVC-POS-'.uniqid(),
    ]);

    $serviceVariant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $serviceProduct->id,
        'sales_price' => 500,
        'purchase_price' => 0,
        'is_default' => true,
    ]);

    $response = $this->postJson('/api/admin/pos/checkout', posCheckoutPayload($this, [
        'items' => [
            [
                'product_variant_id' => $serviceVariant->id,
                'warehouse_id' => null,
                'quantity' => 1,
                'rate' => 500,
                'tax_amount' => 0,
                'discount_amount' => 0,
            ],
        ],
    ]));

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['items.0.product_variant_id']);
});

it('returns warehouses with stock for a variant', function () {
    seedVariantStock($this, $this->warehouse->id, 5);
    seedVariantStock($this, $this->warehouseB->id, 3);

    $response = $this->getJson("/api/admin/pos/variants/{$this->variant->id}/warehouses");

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(2);
    expect(collect($response->json('data'))->pluck('warehouse_id')->all())
        ->toContain($this->warehouse->id, $this->warehouseB->id);
});

it('returns empty warehouse list when variant has no stock', function () {
    $response = $this->getJson("/api/admin/pos/variants/{$this->variant->id}/warehouses");

    $response->assertSuccessful();
    expect($response->json('data'))->toBe([]);
});

it('returns customer default discount in pos customers list', function () {
    $party = Party::create([
        'company_id' => $this->company->id,
        'type' => PartyTypeEnum::CUSTOMER,
        'name' => 'VIP Buyer',
        'code' => 'VIP-1',
        'is_active' => true,
    ]);
    $party->saveDiscount('percent', 12.5);

    $response = $this->getJson('/api/admin/pos/customers?search=VIP');

    $response->assertSuccessful();
    $customer = collect($response->json('data'))->firstWhere('id', $party->id);
    expect($customer)->not->toBeNull();
    expect($customer['discount_type'])->toBe('percent');
    expect((float) $customer['discount_value'])->toBe(12.5);
});

it('requires warehouse on each line item at checkout', function () {
    seedVariantStock($this, $this->warehouse->id, 5);

    $payload = posCheckoutPayload($this);
    unset($payload['items'][0]['warehouse_id']);

    $response = $this->postJson('/api/admin/pos/checkout', $payload);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['items.0.warehouse_id']);
});

it('completes checkout and persists order discount on invoice', function () {
    seedVariantStock($this, $this->warehouse->id, 10);

    $cashAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Cash Sales',
        'code' => 'CASH-POS',
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'cash_sales_account_id' => $cashAccount->id,
    ]);

    $response = $this->postJson('/api/admin/pos/checkout', posCheckoutPayload($this, [
        'order_discount_type' => 'fixed',
        'order_discount_value' => 10,
        'items' => [
            [
                'product_variant_id' => $this->variant->id,
                'warehouse_id' => $this->warehouse->id,
                'quantity' => 2,
                'rate' => 100,
                'tax_amount' => 0,
                'discount_amount' => 5,
                'line_discount_type' => 'fixed',
                'line_discount_value' => 5,
            ],
        ],
    ]));

    $response->assertCreated();
    expect((float) $response->json('data.subtotal'))->toBe(200.0);
    expect((float) $response->json('data.line_discount_total'))->toBe(5.0);
    expect((float) $response->json('data.order_discount_amount'))->toBe(10.0);
    expect((float) $response->json('data.grand_total'))->toBe(185.0);

    $invoice = Invoice::with('discount')->first();
    expect($invoice)->not->toBeNull();
    expect($invoice->discount)->not->toBeNull();
    expect((float) $invoice->discount->amount)->toBe(10.0);
});

it('stores held order json including order discount fields', function () {
    $response = $this->postJson('/api/admin/pos/hold', [
        'label' => 'Table 1',
        'order_data' => [
            'items' => [
                [
                    'variantId' => $this->variant->id,
                    'name' => 'Widget',
                    'quantity' => 1,
                    'rate' => 100,
                    'line_discount_type' => 'fixed',
                    'line_discount_value' => '0',
                ],
            ],
            'order_discount_type' => 'percent',
            'order_discount_value' => '5',
            'warehouseId' => $this->warehouse->id,
            'warehouseName' => 'Main',
        ],
    ]);

    $response->assertCreated();

    $held = PosHeldOrder::first();
    expect($held->order_data['order_discount_type'])->toBe('percent');
    expect($held->order_data['order_discount_value'])->toBe('5');
    expect($held->order_data['warehouseId'])->toBe($this->warehouse->id);
    expect($held->order_data)->not->toHaveKey('shippingAmount');
});

it('reduces stock quantity after successful checkout', function () {
    seedVariantStock($this, $this->warehouse->id, 10);

    $cashAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Cash Sales',
        'code' => 'CASH-POS-2',
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'cash_sales_account_id' => $cashAccount->id,
    ]);

    $this->postJson('/api/admin/pos/checkout', posCheckoutPayload($this, [
        'items' => [
            [
                'product_variant_id' => $this->variant->id,
                'warehouse_id' => $this->warehouse->id,
                'quantity' => 3,
                'rate' => 100,
                'tax_amount' => 0,
                'discount_amount' => 0,
            ],
        ],
    ]))->assertCreated();

    $stock = Stock::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('product_variant_id', $this->variant->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();

    expect($stock->quantity)->toBe(7);
});

it('checkout supports multiple warehouses on one order', function () {
    seedVariantStock($this, $this->warehouse->id, 5);

    $variantB = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'SKU-POS-2',
        'sales_price' => 50,
        'is_default' => false,
    ]);

    seedVariantStock($this, $this->warehouseB->id, 4, $variantB);

    $cashAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Cash Sales Multi',
        'code' => 'CASH-MULTI',
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'cash_sales_account_id' => $cashAccount->id,
    ]);

    $response = $this->postJson('/api/admin/pos/checkout', [
        'payment_method' => 'cash',
        'items' => [
            [
                'product_variant_id' => $this->variant->id,
                'warehouse_id' => $this->warehouse->id,
                'quantity' => 1,
                'rate' => 100,
                'tax_amount' => 0,
                'discount_amount' => 0,
            ],
            [
                'product_variant_id' => $variantB->id,
                'warehouse_id' => $this->warehouseB->id,
                'quantity' => 2,
                'rate' => 50,
                'tax_amount' => 0,
                'discount_amount' => 0,
            ],
        ],
    ]);

    $response->assertCreated();
    expect($response->json('data.warehouse_name'))->toBe('Multiple warehouses');
    expect($response->json('data.warehouses'))->toHaveCount(2);

    $invoice = Invoice::with('invoiceItems')->first();
    expect($invoice->invoiceItems)->toHaveCount(2);
    expect($invoice->invoiceItems->pluck('warehouse_id')->sort()->values()->all())
        ->toBe([$this->warehouse->id, $this->warehouseB->id]);
});
