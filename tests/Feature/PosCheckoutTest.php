<?php

use App\Models\Bill;
use App\Models\User;
use App\Models\Party;
use App\Models\Stock;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\BillItem;
use App\Enums\StatusEnum;
use App\Models\Warehouse;
use App\Models\CreditNote;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\BankAccount;
use App\Models\PaymentMode;
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
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
}

beforeEach(function () {
    Cache::flush();
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

it('receipt item data includes ids required for the return flow', function () {
    seedVariantStock($this, $this->warehouse->id, 5);

    $cashAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Cash Item IDs',
        'code' => 'CASH-IID',
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

    $item = $response->json('data.items.0');
    expect($item)->toHaveKeys(['id', 'product_variant_id', 'warehouse_id']);
    expect($item['product_variant_id'])->toBe($this->variant->id);
    expect($item['warehouse_id'])->toBe($this->warehouse->id);
});

// ─── Return / Refund ─────────────────────────────────────────────────────────

function setupReturnAccounts(object $test, string $suffix = ''): Account
{
    $account = Account::create([
        'company_id' => $test->company->id,
        'account_group_id' => null,
        'name' => 'Return Account '.$suffix,
        'code' => 'RETURN-'.$suffix,
    ]);
    AccountSetting::create([
        'company_id' => $test->company->id,
        'cash_sales_account_id' => $account->id,
        'bank_sales_account_id' => $account->id,
        'customer_account_id' => $account->id,
        'sales_account_id' => $account->id,
    ]);

    return $account;
}

it('processes a pos return and creates an approved credit note', function () {
    seedVariantStock($this, $this->warehouse->id, 5);
    setupReturnAccounts($this, 'A');

    $checkoutRes = $this->postJson('/api/admin/pos/checkout', posCheckoutPayload($this));
    $checkoutRes->assertCreated();
    $invoiceId = $checkoutRes->json('data.id');
    $receiptRes = $this->getJson("/api/admin/pos/receipt/{$invoiceId}");
    $item = $receiptRes->json('data.items.0');

    $response = $this->postJson('/api/admin/pos/return', [
        'invoice_id' => $invoiceId,
        'reason' => 'Defective item',
        'items' => [[
            'invoice_item_id' => $item['id'],
            'product_variant_id' => $item['product_variant_id'],
            'warehouse_id' => $item['warehouse_id'],
            'quantity' => 1,
            'rate' => $item['rate'],
            'tax_amount' => 0,
            'discount_amount' => 0,
        ]],
    ]);

    $response->assertCreated();
    expect($response->json('data'))->toHaveKeys(['credit_note_no', 'refund_total', 'invoice_no', 'items']);
    expect((float) $response->json('data.refund_total'))->toBe(100.0);

    $creditNote = CreditNote::first();
    expect($creditNote)->not->toBeNull();
    expect($creditNote->status->value)->toBe('approved');
    expect($creditNote->invoice_id)->toBe($invoiceId);
    expect($creditNote->remarks)->toBe('Defective item');
});

it('return restores stock to the warehouse', function () {
    seedVariantStock($this, $this->warehouse->id, 5);
    setupReturnAccounts($this, 'B');

    // Sell 2 units → stock becomes 3
    $checkoutRes = $this->postJson('/api/admin/pos/checkout', posCheckoutPayload($this, [
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 2,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
        ]],
    ]));
    $checkoutRes->assertCreated();
    $invoiceId = $checkoutRes->json('data.id');

    $stockBefore = Stock::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('product_variant_id', $this->variant->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->value('quantity');
    expect($stockBefore)->toBe(3);

    $receiptRes = $this->getJson("/api/admin/pos/receipt/{$invoiceId}");
    $item = $receiptRes->json('data.items.0');

    // Return 1 unit → stock should become 4
    $this->postJson('/api/admin/pos/return', [
        'invoice_id' => $invoiceId,
        'items' => [[
            'invoice_item_id' => $item['id'],
            'product_variant_id' => $item['product_variant_id'],
            'warehouse_id' => $item['warehouse_id'],
            'quantity' => 1,
            'rate' => $item['rate'],
            'tax_amount' => 0,
            'discount_amount' => 0,
        ]],
    ])->assertCreated();

    $stockAfter = Stock::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('product_variant_id', $this->variant->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->value('quantity');
    expect($stockAfter)->toBe(4);
});

it('return rejects an invoice that belongs to a different company', function () {
    $otherFiscalYear = FiscalYear::create([
        'year_name' => '2027', 'year_code' => '27',
        'start_date' => '2027-01-01', 'end_date' => '2027-12-31',
    ]);
    $otherCompany = Company::create([
        'fiscal_year_id' => $otherFiscalYear->id,
        'company_name' => 'Other Co',
        'code' => 'OC',
        'inventory_costing_method' => \App\Enums\InventoryCostingMethodEnum::FIFO,
    ]);

    $otherUser = User::create([
        'company_id' => $otherCompany->id,
        'name' => 'Other User',
        'email' => 'other-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $otherWarehouse = Warehouse::create([
        'company_id' => $otherCompany->id,
        'name' => 'Other WH',
        'code' => 'OWH',
    ]);

    $otherAccount = Account::create([
        'company_id' => $otherCompany->id,
        'account_group_id' => null,
        'name' => 'Other Cash',
        'code' => 'OCASH',
    ]);
    AccountSetting::create([
        'company_id' => $otherCompany->id,
        'cash_sales_account_id' => $otherAccount->id,
        'customer_account_id' => $otherAccount->id,
        'sales_account_id' => $otherAccount->id,
    ]);

    $otherProduct = Product::create([
        'company_id' => $otherCompany->id,
        'name' => 'Other Widget',
        'code' => 'OW1',
    ]);
    $otherVariant = ProductVariant::create([
        'company_id' => $otherCompany->id,
        'product_id' => $otherProduct->id,
        'sku' => 'SKU-OTH-1',
        'sales_price' => 100,
        'is_default' => true,
    ]);

    // Create an invoice belonging to the other company by acting as that user
    Sanctum::actingAs($otherUser, ['*'], 'admin');
    seedVariantStock((object) [
        'company' => $otherCompany,
        'fiscalYear' => $otherFiscalYear,
        'user' => $otherUser,
        'warehouse' => $otherWarehouse,
        'variant' => $otherVariant,
    ], $otherWarehouse->id, 5, $otherVariant);

    $otherCheckout = $this->postJson('/api/admin/pos/checkout', [
        'payment_method' => 'cash',
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'items' => [[
            'product_variant_id' => $otherVariant->id,
            'warehouse_id' => $otherWarehouse->id,
            'quantity' => 1,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
        ]],
    ]);
    $otherCheckout->assertCreated();
    $otherInvoiceId = $otherCheckout->json('data.id');

    // Now act as original company user and try to return the other company's invoice
    Sanctum::actingAs($this->user, ['*'], 'admin');

    $response = $this->postJson('/api/admin/pos/return', [
        'invoice_id' => $otherInvoiceId,
        'items' => [[
            'invoice_item_id' => null,
            'product_variant_id' => $otherVariant->id,
            'warehouse_id' => $otherWarehouse->id,
            'quantity' => 1,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
        ]],
    ]);

    // Tenant scope hides the invoice entirely → 404 (not 403, which would reveal it exists)
    $response->assertNotFound();
});

// ─── Transactions endpoint ────────────────────────────────────────────────────

it('transactions endpoint returns invoices for today by default', function () {
    seedVariantStock($this, $this->warehouse->id, 5);
    setupReturnAccounts($this, 'TX1');

    $this->postJson('/api/admin/pos/checkout', posCheckoutPayload($this))->assertCreated();

    $response = $this->getJson('/api/admin/pos/transactions');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('meta.total'))->toBe(1);

    $txn = $response->json('data.0');
    expect($txn)->toHaveKeys(['id', 'invoice_no', 'invoice_date', 'party_name', 'grand_total', 'payment_method', 'has_returns']);
    expect($txn['payment_method'])->toBe('cash');
    expect($txn['has_returns'])->toBeFalse();
    expect((float) $txn['grand_total'])->toBe(100.0);
});

it('transactions endpoint marks has_returns true when a credit note exists', function () {
    seedVariantStock($this, $this->warehouse->id, 5);
    setupReturnAccounts($this, 'TX2');

    $checkoutRes = $this->postJson('/api/admin/pos/checkout', posCheckoutPayload($this))->assertCreated();
    $invoiceId = $checkoutRes->json('data.id');

    $receiptRes = $this->getJson("/api/admin/pos/receipt/{$invoiceId}");
    $item = $receiptRes->json('data.items.0');

    $this->postJson('/api/admin/pos/return', [
        'invoice_id' => $invoiceId,
        'items' => [[
            'invoice_item_id' => $item['id'],
            'product_variant_id' => $item['product_variant_id'],
            'warehouse_id' => $item['warehouse_id'],
            'quantity' => 1,
            'rate' => $item['rate'],
            'tax_amount' => 0,
            'discount_amount' => 0,
        ]],
    ])->assertCreated();

    $response = $this->getJson('/api/admin/pos/transactions');

    $response->assertSuccessful();
    expect($response->json('data.0.has_returns'))->toBeTrue();
});

it('transactions endpoint filters by search term on invoice number', function () {
    seedVariantStock($this, $this->warehouse->id, 10);
    setupReturnAccounts($this, 'TX3');

    $this->postJson('/api/admin/pos/checkout', posCheckoutPayload($this))->assertCreated();
    $this->postJson('/api/admin/pos/checkout', posCheckoutPayload($this))->assertCreated();

    $firstInvoiceNo = Invoice::latest()->skip(1)->value('invoice_no');

    $response = $this->getJson('/api/admin/pos/transactions?search='.urlencode($firstInvoiceNo));

    $response->assertSuccessful();
    expect($response->json('meta.total'))->toBe(1);
    expect($response->json('data.0.invoice_no'))->toBe($firstInvoiceNo);
});

it('transactions endpoint returns empty for a date with no sales', function () {
    $response = $this->getJson('/api/admin/pos/transactions?date_from=2000-01-01&date_to=2000-01-01');

    $response->assertSuccessful();
    expect($response->json('data'))->toBe([]);
    expect($response->json('meta.total'))->toBe(0);
});

it('transactions endpoint respects pagination', function () {
    seedVariantStock($this, $this->warehouse->id, 20);
    setupReturnAccounts($this, 'TX4');

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/admin/pos/checkout', posCheckoutPayload($this))->assertCreated();
    }

    $response = $this->getJson('/api/admin/pos/transactions?limit=2&page=1');
    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('meta.total'))->toBe(5);
    expect($response->json('meta.pages'))->toBe(3);
});

// ─── Nepal Features: Credit Sale & Split Payment ─────────────────────────────

it('credit sale creates an approved invoice with no receipt', function () {
    seedVariantStock($this, $this->warehouse->id, 5);
    setupReturnAccounts($this, 'CR1');

    $response = $this->postJson('/api/admin/pos/checkout', posCheckoutPayload($this, [
        'payment_method' => 'credit',
    ]));

    $response->assertCreated();
    expect($response->json('data.payment_method'))->toBe('credit');
    expect($response->json('data.payments'))->toBe([]);

    $invoice = Invoice::first();
    expect($invoice->status->value)->toBe('approved');
    // No receipt should be created for a credit sale
    expect(\App\Models\Receipt::count())->toBe(0);
});

it('credit sale shows up in transactions as credit payment method', function () {
    seedVariantStock($this, $this->warehouse->id, 5);
    setupReturnAccounts($this, 'CR2');

    $this->postJson('/api/admin/pos/checkout', posCheckoutPayload($this, [
        'payment_method' => 'credit',
    ]))->assertCreated();

    $response = $this->getJson('/api/admin/pos/transactions');
    $response->assertSuccessful();
    expect($response->json('data.0.payment_method'))->toBe('credit');
});

it('split payment creates two receipts allocated to the invoice', function () {
    seedVariantStock($this, $this->warehouse->id, 5);
    setupReturnAccounts($this, 'SP1');

    $response = $this->postJson('/api/admin/pos/checkout', posCheckoutPayload($this, [
        'payment_method' => 'cash',
        'payments' => [
            ['method' => 'cash', 'amount' => 60],
            ['method' => 'card', 'amount' => 40],
        ],
    ]));

    $response->assertCreated();
    expect($response->json('data.payment_method'))->toBe('split');
    expect($response->json('data.payments'))->toHaveCount(2);

    expect(\App\Models\Receipt::count())->toBe(2);

    $invoice = Invoice::first();
    expect($invoice->receiptAllocations)->toHaveCount(2);
    $allocated = $invoice->receiptAllocations->sum('amount');
    expect(round((float) $allocated, 2))->toBe(100.0);
});

it('split payment receipt data shows individual payments on reprint', function () {
    seedVariantStock($this, $this->warehouse->id, 5);
    setupReturnAccounts($this, 'SP2');

    $checkoutRes = $this->postJson('/api/admin/pos/checkout', posCheckoutPayload($this, [
        'payment_method' => 'cash',
        'payments' => [
            ['method' => 'cash', 'amount' => 70],
            ['method' => 'card', 'amount' => 30],
        ],
    ]));
    $checkoutRes->assertCreated();
    $invoiceId = $checkoutRes->json('data.id');

    $response = $this->getJson("/api/admin/pos/receipt/{$invoiceId}");
    $response->assertSuccessful();

    expect($response->json('data.payment_method'))->toBe('split');
    $payments = collect($response->json('data.payments'));
    expect($payments)->toHaveCount(2);
    expect((float) $payments->firstWhere('method', 'cash')['amount'])->toBe(70.0);
    expect((float) $payments->firstWhere('method', 'card')['amount'])->toBe(30.0);
});

// ─── Bank account GL routing ──────────────────────────────────────────────────

it('routes payment to the bank account GL when payment mode has a linked bank account', function () {
    seedVariantStock($this, $this->warehouse->id, 5);

    $cashGl = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Cash GL',
        'code' => 'CASH-GL-BA',
    ]);
    $bankFallbackGl = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Bank Fallback GL',
        'code' => 'BNK-FALLBACK',
    ]);
    $esewaGl = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'eSewa GL',
        'code' => 'ESEWA-GL-BA',
    ]);

    $bankAccount = BankAccount::create([
        'company_id' => $this->company->id,
        'account_id' => $esewaGl->id,
        'bank_name' => 'eSewa',
        'account_number' => '9800000001',
    ]);

    $mode = PaymentMode::create([
        'company_id' => $this->company->id,
        'name' => 'eSewa',
        'is_active' => true,
        'bank_account_id' => $bankAccount->id,
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'cash_sales_account_id' => $cashGl->id,
        'bank_sales_account_id' => $bankFallbackGl->id,
        'customer_account_id' => $cashGl->id,
        'sales_account_id' => $cashGl->id,
    ]);

    $response = $this->postJson('/api/admin/pos/checkout', posCheckoutPayload($this, [
        'payment_method' => 'esewa',
        'payment_mode_id' => $mode->id,
    ]));

    $response->assertCreated();
    $receipt = Receipt::first();
    expect($receipt)->not->toBeNull();
    expect($receipt->account_id)->toBe($esewaGl->id);
});

it('falls back to bank_sales_account_id when payment mode has no linked bank account', function () {
    seedVariantStock($this, $this->warehouse->id, 5);

    $cashGl = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Cash GL',
        'code' => 'CASH-GL-FB',
    ]);
    $bankFallbackGl = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Bank Fallback GL',
        'code' => 'BNK-FB',
    ]);

    $mode = PaymentMode::create([
        'company_id' => $this->company->id,
        'name' => 'Card',
        'is_active' => true,
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'cash_sales_account_id' => $cashGl->id,
        'bank_sales_account_id' => $bankFallbackGl->id,
        'customer_account_id' => $cashGl->id,
        'sales_account_id' => $cashGl->id,
    ]);

    $response = $this->postJson('/api/admin/pos/checkout', posCheckoutPayload($this, [
        'payment_method' => 'card',
        'payment_mode_id' => $mode->id,
    ]));

    $response->assertCreated();
    $receipt = Receipt::first();
    expect($receipt)->not->toBeNull();
    expect($receipt->account_id)->toBe($bankFallbackGl->id);
});

it('routes cash payment to cash_sales_account regardless of payment_mode_id', function () {
    seedVariantStock($this, $this->warehouse->id, 5);

    $cashGl = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Cash GL',
        'code' => 'CASH-GL-CASH',
    ]);
    $bankFallbackGl = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Bank Fallback GL',
        'code' => 'BNK-CASH',
    ]);

    $cashMode = PaymentMode::create([
        'company_id' => $this->company->id,
        'name' => 'Cash',
        'is_active' => true,
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'cash_sales_account_id' => $cashGl->id,
        'bank_sales_account_id' => $bankFallbackGl->id,
        'customer_account_id' => $cashGl->id,
        'sales_account_id' => $cashGl->id,
    ]);

    $response = $this->postJson('/api/admin/pos/checkout', posCheckoutPayload($this, [
        'payment_method' => 'cash',
        'payment_mode_id' => $cashMode->id,
    ]));

    $response->assertCreated();
    $receipt = Receipt::first();
    expect($receipt)->not->toBeNull();
    expect($receipt->account_id)->toBe($cashGl->id);
});

it('routes each split payment line to its own bank account GL', function () {
    seedVariantStock($this, $this->warehouse->id, 5);

    $cashGl = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Cash GL',
        'code' => 'CASH-GL-SP',
    ]);
    $esewaGl = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'eSewa GL',
        'code' => 'ESEWA-GL-SP',
    ]);
    $bankFallbackGl = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Bank Fallback GL',
        'code' => 'BNK-SP',
    ]);

    $esewaBank = BankAccount::create([
        'company_id' => $this->company->id,
        'account_id' => $esewaGl->id,
        'bank_name' => 'eSewa',
        'account_number' => '9800000002',
    ]);
    $cashMode = PaymentMode::create([
        'company_id' => $this->company->id,
        'name' => 'Cash',
        'is_active' => true,
    ]);
    $esewaMode = PaymentMode::create([
        'company_id' => $this->company->id,
        'name' => 'eSewa',
        'is_active' => true,
        'bank_account_id' => $esewaBank->id,
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'cash_sales_account_id' => $cashGl->id,
        'bank_sales_account_id' => $bankFallbackGl->id,
        'customer_account_id' => $cashGl->id,
        'sales_account_id' => $cashGl->id,
    ]);

    $response = $this->postJson('/api/admin/pos/checkout', posCheckoutPayload($this, [
        'payment_method' => 'split',
        'payments' => [
            ['method' => 'cash',  'payment_mode_id' => $cashMode->id,  'amount' => 60],
            ['method' => 'esewa', 'payment_mode_id' => $esewaMode->id, 'amount' => 40],
        ],
    ]));

    $response->assertCreated();
    $receipts = Receipt::all();
    expect($receipts)->toHaveCount(2);

    $cashReceipt = $receipts->firstWhere('payment_method', 'cash');
    $esewaReceipt = $receipts->firstWhere('payment_method', 'esewa');

    expect($cashReceipt->account_id)->toBe($cashGl->id);
    expect($esewaReceipt->account_id)->toBe($esewaGl->id);
});
