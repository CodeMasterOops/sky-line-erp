<?php

use App\Models\Bill;
use App\Models\User;
use App\Models\Party;
use App\Models\Stock;
use App\Models\Account;
use App\Models\Company;
use App\Models\Product;
use App\Models\BillItem;
use App\Enums\StatusEnum;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Models\SalesOrder;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Enums\ChangeTypeEnum;
use App\Models\StockMovement;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Models\SalesOrderItem;
use App\Models\DeliveryChallan;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use App\Services\Inventory\InventoryCostCalculator;
use App\Services\Inventory\InventoryLayerReceiptService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function dcWarmAllTablesCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $tables[$table] = Schema::getColumnListing($table);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

function dcSeedVariantStock(object $test, int $warehouseId, int $quantity): void
{
    dcWarmAllTablesCache();

    $bill = Bill::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'bill_no' => 'BILL-DC-'.uniqid(),
        'bill_date' => now()->toDateString(),
        'create_user_id' => $test->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $item = BillItem::create([
        'bill_id' => $bill->id,
        'product_variant_id' => $test->variant->id,
        'warehouse_id' => $warehouseId,
        'quantity' => $quantity,
        'rate' => 50,
        'discount_amount' => 0,
    ]);

    $receipt = app(InventoryLayerReceiptService::class);

    DB::transaction(function () use ($receipt, $test, $bill, $item, $warehouseId, $quantity) {
        $receipt->receive(
            $test->company,
            $bill,
            $test->variant->id,
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

function dcSeedAccounts(Company $company): void
{
    $sales = Account::create([
        'company_id' => $company->id,
        'account_group_id' => null,
        'name' => 'Sales',
        'code' => 'SALES-DC',
    ]);
    $customer = Account::create([
        'company_id' => $company->id,
        'account_group_id' => null,
        'name' => 'Customers',
        'code' => 'AR-DC',
    ]);

    AccountSetting::create([
        'company_id' => $company->id,
        'sales_account_id' => $sales->id,
        'customer_account_id' => $customer->id,
    ]);
}

function dcPayload(object $test, array $overrides = []): array
{
    return array_merge([
        'warehouse_id' => $test->warehouse->id,
        'challan_date' => now()->toDateString(),
        'status' => StatusEnum::DRAFT->value,
        'items' => [
            [
                'product_variant_id' => $test->variant->id,
                'quantity' => 2,
                'rate' => 100,
            ],
        ],
    ], $overrides);
}

beforeEach(function () {
    dcWarmAllTablesCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'DC Test Co',
        'code' => 'DCTC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'DC Tester',
        'email' => 'dc-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Main',
        'code' => 'W1',
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Widget',
        'code' => 'WIDGET-DC',
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'SKU-DC-1',
        'sales_price' => 100,
        'is_default' => true,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

it('creates a draft delivery challan', function () {
    $response = $this->postJson('/api/admin/delivery-challan', dcPayload($this));

    $response->assertCreated();
    expect(DeliveryChallan::count())->toBe(1);
    expect(DeliveryChallan::first()->status)->toBe(StatusEnum::DRAFT);
});

it('approves delivery challan and deducts stock', function () {
    dcSeedVariantStock($this, $this->warehouse->id, 10);

    $create = $this->postJson('/api/admin/delivery-challan', dcPayload($this));
    $create->assertCreated();
    $challanId = $create->json('data.id');

    $beforeMovements = StockMovement::withoutGlobalScopes()
        ->where('type', ChangeTypeEnum::DELIVERY)
        ->count();

    $approve = $this->postJson("/api/admin/delivery-challan/{$challanId}/approve");
    $approve->assertSuccessful();

    $stock = Stock::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('product_variant_id', $this->variant->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();

    expect($stock->quantity)->toBe(8);
    expect(
        StockMovement::withoutGlobalScopes()
            ->where('type', ChangeTypeEnum::DELIVERY)
            ->count()
    )->toBe($beforeMovements + 1);
});

it('fails approval when stock is insufficient', function () {
    $create = $this->postJson('/api/admin/delivery-challan', dcPayload($this));
    $create->assertCreated();
    $challanId = $create->json('data.id');

    $approve = $this->postJson("/api/admin/delivery-challan/{$challanId}/approve");
    $approve->assertUnprocessable();
});

it('blocks editing and deleting approved delivery challans', function () {
    dcSeedVariantStock($this, $this->warehouse->id, 10);

    $create = $this->postJson('/api/admin/delivery-challan', dcPayload($this, [
        'status' => StatusEnum::APPROVED->value,
    ]));
    $create->assertCreated();
    $challanId = $create->json('data.id');

    $this->putJson("/api/admin/delivery-challan/{$challanId}", dcPayload($this))
        ->assertUnprocessable();

    $this->deleteJson("/api/admin/delivery-challan/{$challanId}")
        ->assertUnprocessable();
});

it('caps delivery challan quantity against sales order remaining qty', function () {
    dcSeedVariantStock($this, $this->warehouse->id, 20);

    $party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Customer',
        'code' => 'CUST-DC-1',
        'type' => PartyTypeEnum::CUSTOMER,
    ]);

    $order = SalesOrder::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $party->id,
        'order_no' => 'SO-00001',
        'order_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
        'approved_at' => now(),
        'approve_user_id' => $this->user->id,
    ]);

    $soItem = SalesOrderItem::create([
        'sales_order_id' => $order->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 5,
        'rate' => 100,
    ]);

    $response = $this->postJson('/api/admin/delivery-challan', dcPayload($this, [
        'sales_order_id' => $order->id,
        'party_id' => $party->id,
        'items' => [
            [
                'product_variant_id' => $this->variant->id,
                'sales_order_item_id' => $soItem->id,
                'quantity' => 6,
                'rate' => 100,
            ],
        ],
    ]));

    $response->assertUnprocessable();
});

it('does not double deduct stock when invoicing delivery challan lines', function () {
    dcSeedVariantStock($this, $this->warehouse->id, 10);
    dcSeedAccounts($this->company);

    $create = $this->postJson('/api/admin/delivery-challan', dcPayload($this, [
        'status' => StatusEnum::APPROVED->value,
    ]));
    $create->assertCreated();

    $dcItemId = DB::table('delivery_challan_items')->value('id');

    $stockAfterDc = Stock::withoutGlobalScopes()
        ->where('product_variant_id', $this->variant->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->value('quantity');

    $saleMovementsBefore = StockMovement::withoutGlobalScopes()
        ->where('type', ChangeTypeEnum::SALE)
        ->count();

    $invoice = $this->postJson('/api/admin/invoice', [
        'invoice_date' => now()->toDateString(),
        'status' => StatusEnum::APPROVED->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'items' => [
            [
                'product_variant_id' => $this->variant->id,
                'delivery_challan_item_id' => $dcItemId,
                'warehouse_id' => $this->warehouse->id,
                'quantity' => 2,
                'rate' => 100,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'tax_line_type' => 'taxable',
                'line_discount_type' => 'fixed',
                'line_discount_value' => 0,
            ],
        ],
    ]);

    $invoice->assertCreated();

    $stockAfterInvoice = Stock::withoutGlobalScopes()
        ->where('product_variant_id', $this->variant->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->value('quantity');

    expect($stockAfterInvoice)->toBe($stockAfterDc);
    expect(
        StockMovement::withoutGlobalScopes()
            ->where('type', ChangeTypeEnum::SALE)
            ->count()
    )->toBe($saleMovementsBefore);
});

it('still deducts stock for standalone approved invoices', function () {
    dcSeedVariantStock($this, $this->warehouse->id, 10);
    dcSeedAccounts($this->company);

    $invoice = $this->postJson('/api/admin/invoice', [
        'invoice_date' => now()->toDateString(),
        'status' => StatusEnum::APPROVED->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'items' => [
            [
                'product_variant_id' => $this->variant->id,
                'warehouse_id' => $this->warehouse->id,
                'quantity' => 2,
                'rate' => 100,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'tax_line_type' => 'taxable',
                'line_discount_type' => 'fixed',
                'line_discount_value' => 0,
            ],
        ],
    ]);

    $invoice->assertCreated();

    $stock = Stock::withoutGlobalScopes()
        ->where('product_variant_id', $this->variant->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();

    expect($stock->quantity)->toBe(8);
});

it('returns deliverable items for an approved sales order', function () {
    $party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Customer Two',
        'code' => 'CUST-DC-2',
        'type' => PartyTypeEnum::CUSTOMER,
    ]);

    $order = SalesOrder::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $party->id,
        'order_no' => 'SO-00002',
        'order_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
        'approved_at' => now(),
        'approve_user_id' => $this->user->id,
    ]);

    SalesOrderItem::create([
        'sales_order_id' => $order->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 4,
        'rate' => 100,
    ]);

    $response = $this->getJson("/api/admin/sales-order/{$order->id}/deliverable-items");
    $response->assertSuccessful();
    expect($response->json('data.items.0.remaining_qty'))->toEqual(4);
});
