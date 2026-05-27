<?php

use App\Models\User;
use App\Models\Party;
use App\Models\Stock;
use App\Models\Account;
use App\Models\Company;
use App\Models\GrnItem;
use App\Models\Journal;
use App\Models\Product;
use App\Enums\StatusEnum;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Enums\ChangeTypeEnum;
use App\Models\StockMovement;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Services\TenantService;
use App\Models\GoodsReceivedNote;
use App\Enums\GrnBillingStatusEnum;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function grnWarmAllTablesCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $tables[$table] = Schema::getColumnListing($table);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

function grnSeedAccounts(Company $company): AccountSetting
{
    $inventory = Account::create([
        'company_id' => $company->id,
        'account_group_id' => null,
        'name' => 'Inventory',
        'code' => 'INV-GRN',
    ]);
    $purchase = Account::create([
        'company_id' => $company->id,
        'account_group_id' => null,
        'name' => 'Purchase',
        'code' => 'PUR-GRN',
    ]);
    $grni = Account::create([
        'company_id' => $company->id,
        'account_group_id' => null,
        'name' => 'GRNI',
        'code' => 'GRNI-GRN',
    ]);
    $supplier = Account::create([
        'company_id' => $company->id,
        'account_group_id' => null,
        'name' => 'Suppliers',
        'code' => 'AP-GRN',
    ]);
    $vat = Account::create([
        'company_id' => $company->id,
        'account_group_id' => null,
        'name' => 'VAT',
        'code' => 'VAT-GRN',
    ]);

    return AccountSetting::create([
        'company_id' => $company->id,
        'inventory_account_id' => $inventory->id,
        'purchase_account_id' => $purchase->id,
        'grni_account_id' => $grni->id,
        'supplier_account_id' => $supplier->id,
        'vat_account_id' => $vat->id,
    ]);
}

function grnPayload(object $test, array $overrides = []): array
{
    return array_merge([
        'party_id' => $test->supplier->id,
        'warehouse_id' => $test->warehouse->id,
        'received_date' => now()->toDateString(),
        'items' => [
            [
                'product_variant_id' => $test->variant->id,
                'ordered_qty' => 0,
                'received_qty' => 5,
                'unit_cost' => 50,
            ],
        ],
    ], $overrides);
}

beforeEach(function () {
    grnWarmAllTablesCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'GRN Test Co',
        'code' => 'GRNTC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'GRN Tester',
        'email' => 'grn-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Main',
        'code' => 'W1',
    ]);

    $this->supplier = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Supplier',
        'code' => 'SUP-GRN',
        'type' => PartyTypeEnum::SUPPLIER,
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Widget',
        'code' => 'WIDGET-GRN',
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'SKU-GRN-1',
        'sales_price' => 100,
        'is_default' => true,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

it('creates a draft grn', function () {
    $response = $this->postJson('/api/admin/grn', grnPayload($this));

    $response->assertCreated();
    expect(GoodsReceivedNote::count())->toBe(1);
    expect(GoodsReceivedNote::first()->status)->toBe(StatusEnum::DRAFT);
});

it('approves grn and increases stock without duplicate on re-approve attempt', function () {
    grnSeedAccounts($this->company);

    $create = $this->postJson('/api/admin/grn', grnPayload($this));
    $create->assertCreated();
    $grnId = $create->json('data.id');

    $approve = $this->postJson("/api/admin/grn/{$grnId}/approve");
    $approve->assertSuccessful();

    $stock = Stock::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('product_variant_id', $this->variant->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();

    expect($stock)->not->toBeNull();
    expect($stock->quantity)->toBe(5);

    expect(
        StockMovement::withoutGlobalScopes()
            ->where('type', ChangeTypeEnum::GRN_RECEIPT)
            ->count()
    )->toBe(1);

    $this->postJson("/api/admin/grn/{$grnId}/approve")->assertUnprocessable();
});

it('posts grni journal when grn is approved and accounts are configured', function () {
    grnSeedAccounts($this->company);

    $create = $this->postJson('/api/admin/grn', grnPayload($this));
    $grnId = $create->json('data.id');

    $this->postJson("/api/admin/grn/{$grnId}/approve")->assertSuccessful();

    $movement = StockMovement::withoutGlobalScopes()
        ->where('type', ChangeTypeEnum::GRN_RECEIPT)
        ->first();

    expect($movement)->not->toBeNull();
    expect($movement->gl_journal_id)->not->toBeNull();

    $journal = Journal::withoutGlobalScopes()->find($movement->gl_journal_id);
    expect($journal)->not->toBeNull();
});

it('bills grn without double stock receipt', function () {
    grnSeedAccounts($this->company);

    $create = $this->postJson('/api/admin/grn', grnPayload($this));
    $grnId = $create->json('data.id');
    $this->postJson("/api/admin/grn/{$grnId}/approve")->assertSuccessful();

    $grnItemId = GrnItem::value('id');
    $stockBeforeBill = Stock::withoutGlobalScopes()
        ->where('product_variant_id', $this->variant->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->value('quantity');

    $billResponse = $this->postJson('/api/admin/bill', [
        'bill_date' => now()->toDateString(),
        'party_id' => $this->supplier->id,
        'status' => StatusEnum::APPROVED->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'items' => [
            [
                'product_variant_id' => $this->variant->id,
                'warehouse_id' => $this->warehouse->id,
                'grn_item_id' => $grnItemId,
                'quantity' => 5,
                'rate' => 55,
                'line_discount_type' => 'fixed',
                'line_discount_value' => 0,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'tax_line_type' => 'taxable',
            ],
        ],
    ]);

    $billResponse->assertCreated();

    $stockAfterBill = Stock::withoutGlobalScopes()
        ->where('product_variant_id', $this->variant->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->value('quantity');

    expect($stockAfterBill)->toBe($stockBeforeBill);

    expect(
        StockMovement::withoutGlobalScopes()
            ->where('type', ChangeTypeEnum::PURCHASE)
            ->count()
    )->toBe(0);

    $grnItem = GrnItem::find($grnItemId);
    expect((float) $grnItem->billed_qty)->toBe(5.0);

    $grn = GoodsReceivedNote::find($grnId);
    expect($grn->billing_status)->toBe(GrnBillingStatusEnum::FULLY_BILLED);
});

it('rejects bill quantity exceeding remaining grn quantity', function () {
    grnSeedAccounts($this->company);

    $create = $this->postJson('/api/admin/grn', grnPayload($this));
    $grnId = $create->json('data.id');
    $this->postJson("/api/admin/grn/{$grnId}/approve")->assertSuccessful();

    $grnItemId = GrnItem::value('id');

    $this->postJson('/api/admin/bill', [
        'bill_date' => now()->toDateString(),
        'party_id' => $this->supplier->id,
        'status' => StatusEnum::APPROVED->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'items' => [
            [
                'product_variant_id' => $this->variant->id,
                'warehouse_id' => $this->warehouse->id,
                'grn_item_id' => $grnItemId,
                'quantity' => 6,
                'rate' => 55,
                'line_discount_type' => 'fixed',
                'line_discount_value' => 0,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'tax_line_type' => 'taxable',
            ],
        ],
    ])->assertUnprocessable();
});

it('lists billable grn items for supplier', function () {
    grnSeedAccounts($this->company);

    $create = $this->postJson('/api/admin/grn', grnPayload($this));
    $grnId = $create->json('data.id');
    $this->postJson("/api/admin/grn/{$grnId}/approve")->assertSuccessful();

    $response = $this->getJson('/api/admin/grn/billable-items?party_id='.$this->supplier->id);

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.remaining_qty'))->toBe(5);
});

it('keeps direct bill stock receipt when no grn link', function () {
    grnSeedAccounts($this->company);

    $beforeMovements = StockMovement::withoutGlobalScopes()
        ->where('type', ChangeTypeEnum::PURCHASE)
        ->count();

    $this->postJson('/api/admin/bill', [
        'bill_date' => now()->toDateString(),
        'party_id' => $this->supplier->id,
        'status' => StatusEnum::APPROVED->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'items' => [
            [
                'product_variant_id' => $this->variant->id,
                'warehouse_id' => $this->warehouse->id,
                'quantity' => 3,
                'rate' => 40,
                'line_discount_type' => 'fixed',
                'line_discount_value' => 0,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'tax_line_type' => 'taxable',
            ],
        ],
    ])->assertCreated();

    expect(
        StockMovement::withoutGlobalScopes()
            ->where('type', ChangeTypeEnum::PURCHASE)
            ->count()
    )->toBe($beforeMovements + 1);

    $stock = Stock::withoutGlobalScopes()
        ->where('product_variant_id', $this->variant->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();

    expect($stock->quantity)->toBe(3);
});
