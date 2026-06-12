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

it('blocks checkout when GL control accounts are not configured', function () {
    seedVariantStock($this, $this->warehouse->id, 5);

    $response = $this->postJson('/api/admin/pos/checkout', posCheckoutPayload($this, [
        'items' => [
            [
                'product_variant_id' => $this->variant->id,
                'warehouse_id' => $this->warehouse->id,
                'quantity' => 1,
                'rate' => 100,
                'tax_amount' => 0,
                'discount_amount' => 0,
            ],
        ],
    ]));

    $response->assertStatus(422);
    expect(Invoice::count())->toBe(0);
});

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
        'customer_account_id' => $cashAccount->id,
        'sales_account_id' => $cashAccount->id,
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
        'customer_account_id' => $cashAccount->id,
        'sales_account_id' => $cashAccount->id,
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
        'customer_account_id' => $cashAccount->id,
        'sales_account_id' => $cashAccount->id,
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

it('returns invoice_date_bs and taxable_amount in checkout response', function () {
    seedVariantStock($this, $this->warehouse->id, 5);

    $cashAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Cash BS',
        'code' => 'CASH-BS',
    ]);
    AccountSetting::create([
        'company_id' => $this->company->id,
        'cash_sales_account_id' => $cashAccount->id,
        'customer_account_id' => $cashAccount->id,
        'sales_account_id' => $cashAccount->id,
    ]);

    $response = $this->postJson('/api/admin/pos/checkout', posCheckoutPayload($this, [
        'order_discount_type' => 'fixed',
        'order_discount_value' => 5,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 1,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
        ]],
    ]));

    $response->assertCreated();
    expect($response->json('data'))->toHaveKeys(['invoice_date_bs', 'taxable_amount', 'party_pan']);
    expect((float) $response->json('data.taxable_amount'))->toBe(95.0);
});

it('returns customer pan in pos customers list', function () {
    $party = Party::create([
        'company_id' => $this->company->id,
        'type' => PartyTypeEnum::CUSTOMER,
        'name' => 'PAN Customer',
        'code' => 'PAN-C1',
        'pan' => '123456789',
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/admin/pos/customers?search=PAN Customer');

    $response->assertSuccessful();
    $customer = collect($response->json('data'))->firstWhere('id', $party->id);
    expect($customer['pan'])->toBe('123456789');
});

// ─── Till / Cash Register ─────────────────────────────────────────────────────

it('opens a new till session with the supplied opening cash', function () {
    $response = $this->postJson('/api/admin/pos/till/open', ['opening_cash' => 500]);

    $response->assertCreated();
    expect($response->json('data.status'))->toBe('open');
    expect((float) $response->json('data.opening_cash'))->toBe(500.0);
    expect($response->json('data.closed_at'))->toBeNull();

    expect(\App\Models\PosSession::count())->toBe(1);
});

it('returns the existing session when a till is already open', function () {
    $this->postJson('/api/admin/pos/till/open', ['opening_cash' => 100])->assertCreated();

    $response = $this->postJson('/api/admin/pos/till/open', ['opening_cash' => 999]);

    $response->assertOk();
    expect(\App\Models\PosSession::count())->toBe(1);
    expect((float) $response->json('data.opening_cash'))->toBe(100.0);
});

it('rejects open-till when opening_cash is missing', function () {
    $response = $this->postJson('/api/admin/pos/till/open', []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['opening_cash']);
});

it('returns null from current-session when no till is open', function () {
    $response = $this->getJson('/api/admin/pos/till/current');

    $response->assertOk();
    expect($response->json('data'))->toBeNull();
});

it('returns the open session from current-session endpoint', function () {
    $this->postJson('/api/admin/pos/till/open', ['opening_cash' => 250])->assertCreated();

    $response = $this->getJson('/api/admin/pos/till/current');

    $response->assertOk();
    expect($response->json('data.status'))->toBe('open');
    expect((float) $response->json('data.opening_cash'))->toBe(250.0);
});

it('records a cash-in movement against the open session', function () {
    $this->postJson('/api/admin/pos/till/open', ['opening_cash' => 200])->assertCreated();

    $response = $this->postJson('/api/admin/pos/till/cash-movement', [
        'type' => 'cash_in',
        'amount' => 50,
        'reason' => 'Petty cash top-up',
    ]);

    $response->assertCreated();
    expect($response->json('data.type'))->toBe('cash_in');
    expect((float) $response->json('data.amount'))->toBe(50.0);
    expect(\App\Models\PosCashMovement::count())->toBe(1);
});

it('records a cash-out movement against the open session', function () {
    $this->postJson('/api/admin/pos/till/open', ['opening_cash' => 300])->assertCreated();

    $response = $this->postJson('/api/admin/pos/till/cash-movement', [
        'type' => 'cash_out',
        'amount' => 75,
        'reason' => 'Float withdrawal',
    ]);

    $response->assertCreated();
    expect($response->json('data.type'))->toBe('cash_out');
    expect((float) $response->json('data.amount'))->toBe(75.0);
});

it('rejects a cash movement when no till is open', function () {
    $response = $this->postJson('/api/admin/pos/till/cash-movement', [
        'type' => 'cash_in',
        'amount' => 50,
    ]);

    $response->assertUnprocessable();
});

it('rejects a cash movement with an invalid type', function () {
    $this->postJson('/api/admin/pos/till/open', ['opening_cash' => 100])->assertCreated();

    $response = $this->postJson('/api/admin/pos/till/cash-movement', [
        'type' => 'transfer',
        'amount' => 50,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['type']);
});

it('closes the till and calculates expected cash and difference correctly', function () {
    $this->postJson('/api/admin/pos/till/open', ['opening_cash' => 1000])->assertCreated();

    // Cash in 200, cash out 50 → expected = 1000 + 0 (no sales) + 200 - 50 = 1150
    $this->postJson('/api/admin/pos/till/cash-movement', ['type' => 'cash_in', 'amount' => 200, 'reason' => 'top up']);
    $this->postJson('/api/admin/pos/till/cash-movement', ['type' => 'cash_out', 'amount' => 50, 'reason' => 'withdrawal']);

    $response = $this->postJson('/api/admin/pos/till/close', [
        'closing_cash' => 1200,
        'notes' => 'End of day',
    ]);

    $response->assertOk();
    expect($response->json('data.status'))->toBe('closed');
    expect((float) $response->json('data.expected_cash'))->toBe(1150.0);
    expect((float) $response->json('data.closing_cash'))->toBe(1200.0);
    expect((float) $response->json('data.cash_difference'))->toBe(50.0);
    expect($response->json('data.closed_at'))->not->toBeNull();

    expect(\App\Models\PosSession::where('status', 'closed')->count())->toBe(1);
});

it('returns 422 when closing a till with no open session', function () {
    $response = $this->postJson('/api/admin/pos/till/close', ['closing_cash' => 500]);

    $response->assertUnprocessable();
});

it('returns null from till summary when no session is open', function () {
    $response = $this->getJson('/api/admin/pos/till/summary');

    $response->assertOk();
    expect($response->json('data'))->toBeNull();
});

it('till summary reflects cash movements correctly', function () {
    $this->postJson('/api/admin/pos/till/open', ['opening_cash' => 500])->assertCreated();
    $this->postJson('/api/admin/pos/till/cash-movement', ['type' => 'cash_in', 'amount' => 100, 'reason' => 'top-up']);
    $this->postJson('/api/admin/pos/till/cash-movement', ['type' => 'cash_out', 'amount' => 30, 'reason' => 'petty']);

    $response = $this->getJson('/api/admin/pos/till/summary');

    $response->assertOk();
    expect($response->json('data.status'))->toBe('open');
    expect((float) $response->json('data.cash_ins'))->toBe(100.0);
    expect((float) $response->json('data.cash_outs'))->toBe(30.0);
    // expected = 500 + 0 (no cash sales) + 100 - 30 = 570
    expect((float) $response->json('data.expected_cash'))->toBe(570.0);
    expect($response->json('data.movements'))->toHaveCount(2);
});

it('returns receipt data for reprinting an existing invoice', function () {
    seedVariantStock($this, $this->warehouse->id, 5);

    $cashAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Cash Reprint',
        'code' => 'CASH-RP',
    ]);
    AccountSetting::create([
        'company_id' => $this->company->id,
        'cash_sales_account_id' => $cashAccount->id,
        'customer_account_id' => $cashAccount->id,
        'sales_account_id' => $cashAccount->id,
    ]);

    $checkoutRes = $this->postJson('/api/admin/pos/checkout', posCheckoutPayload($this));
    $checkoutRes->assertCreated();
    $invoiceId = $checkoutRes->json('data.id');

    $response = $this->getJson("/api/admin/pos/receipt/{$invoiceId}");

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveKeys([
        'invoice_no', 'invoice_date_bs', 'taxable_amount', 'grand_total', 'items',
    ]);
    expect($response->json('data.grand_total'))->toBe($checkoutRes->json('data.grand_total'));
});
