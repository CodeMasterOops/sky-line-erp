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
use App\Models\LandedCost;
use App\Models\StockLayer;
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
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
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

it('capitalizes landed costs into grn stock layers and posts clearing journal', function () {
    $settings = grnSeedAccounts($this->company);
    $clearing = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Landed Cost Clearing',
        'code' => 'LC-CLEAR-GRN',
    ]);

    $create = $this->postJson('/api/admin/grn', grnPayload($this, [
        'landed_costs' => [
            [
                'cost_type' => 'transport',
                'description' => 'Transport to warehouse',
                'treatment' => 'capitalized',
                'allocation_method' => 'quantity',
                'amount' => 50,
                'account_id' => $clearing->id,
            ],
        ],
    ]));
    $grnId = $create->json('data.id');

    $this->postJson("/api/admin/grn/{$grnId}/approve")->assertSuccessful();

    $landedCost = LandedCost::with('allocations')->first();
    expect($landedCost)->not->toBeNull();
    expect($landedCost->journal_id)->not->toBeNull();
    expect($landedCost->allocations)->toHaveCount(1);
    expect((float) $landedCost->allocations->first()->allocated_amount)->toBe(50.0);
    expect((float) $landedCost->allocations->first()->allocated_unit_cost)->toBe(10.0);

    $layer = StockLayer::withoutGlobalScopes()
        ->where('source_grn_item_id', GrnItem::value('id'))
        ->first();

    expect($layer)->not->toBeNull();
    expect((float) $layer->base_unit_cost)->toBe(50.0);
    expect((float) $layer->landed_unit_cost)->toBe(10.0);
    expect((float) $layer->unit_cost)->toBe(60.0);

    $journal = Journal::withoutGlobalScopes()
        ->with('journalItems')
        ->find($landedCost->journal_id);

    expect($journal)->not->toBeNull();
    expect((float) $journal->journalItems->where('account_id', $settings->inventory_account_id)->sum('dr_amount'))->toBe(50.0);
    expect((float) $journal->journalItems->where('account_id', $clearing->id)->sum('cr_amount'))->toBe(50.0);
});

it('keeps claimable vat out of capitalized landed cost', function () {
    $settings = grnSeedAccounts($this->company);
    $clearing = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'VAT Landed Clearing',
        'code' => 'LC-VAT-GRN',
    ]);

    $create = $this->postJson('/api/admin/grn', grnPayload($this, [
        'landed_costs' => [
            [
                'cost_type' => 'freight',
                'treatment' => 'capitalized',
                'allocation_method' => 'quantity',
                'amount' => 100,
                'vat_amount' => 13,
                'vat_claimable_amount' => 13,
                'account_id' => $clearing->id,
            ],
        ],
    ]));
    $grnId = $create->json('data.id');

    $this->postJson("/api/admin/grn/{$grnId}/approve")->assertSuccessful();

    $layer = StockLayer::withoutGlobalScopes()
        ->where('source_grn_item_id', GrnItem::value('id'))
        ->first();

    expect((float) $layer->landed_unit_cost)->toBe(20.0);
    expect((float) $layer->unit_cost)->toBe(70.0);

    $landedCost = LandedCost::first();
    $journal = Journal::withoutGlobalScopes()
        ->with('journalItems')
        ->find($landedCost->journal_id);

    expect((float) $journal->journalItems->where('account_id', $settings->inventory_account_id)->sum('dr_amount'))->toBe(100.0);
    expect((float) $journal->journalItems->where('account_id', $settings->vat_account_id)->sum('dr_amount'))->toBe(13.0);
    expect((float) $journal->journalItems->where('account_id', $clearing->id)->sum('cr_amount'))->toBe(113.0);
});

it('posts expense landed costs without changing inventory value', function () {
    $settings = grnSeedAccounts($this->company);
    $expense = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Procurement Expense',
        'code' => 'PROC-EXP-GRN',
    ]);

    $create = $this->postJson('/api/admin/grn', grnPayload($this, [
        'landed_costs' => [
            [
                'cost_type' => 'admin',
                'treatment' => 'expense',
                'amount' => 30,
                'vat_amount' => 3,
                'vat_claimable_amount' => 3,
                'account_id' => $expense->id,
            ],
        ],
    ]));
    $grnId = $create->json('data.id');

    $this->postJson("/api/admin/grn/{$grnId}/approve")->assertSuccessful();

    $layer = StockLayer::withoutGlobalScopes()
        ->where('source_grn_item_id', GrnItem::value('id'))
        ->first();

    expect((float) $layer->landed_unit_cost)->toBe(0.0);
    expect((float) $layer->unit_cost)->toBe(50.0);

    $landedCost = LandedCost::with('allocations')->first();
    expect($landedCost->allocations)->toHaveCount(0);

    $journal = Journal::withoutGlobalScopes()
        ->with('journalItems')
        ->find($landedCost->journal_id);

    expect((float) $journal->journalItems->where('account_id', $expense->id)->sum('dr_amount'))->toBe(30.0);
    expect((float) $journal->journalItems->where('account_id', $settings->vat_account_id)->sum('dr_amount'))->toBe(3.0);
    expect((float) $journal->journalItems->where('account_id', $settings->supplier_account_id)->sum('cr_amount'))->toBe(33.0);
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

it('capitalizes landed costs on direct purchase bill approval', function () {
    grnSeedAccounts($this->company);

    $clearing = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Freight Clearing',
        'code' => 'FR-BILL',
    ]);

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
                'quantity' => 4,
                'rate' => 25,
                'line_discount_type' => 'fixed',
                'line_discount_value' => 0,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'tax_line_type' => 'taxable',
            ],
        ],
        'landed_costs' => [
            [
                'cost_type' => 'transport',
                'treatment' => 'capitalized',
                'allocation_method' => 'quantity',
                'amount' => 40,
                'account_id' => $clearing->id,
            ],
        ],
    ])->assertCreated();

    $landedCost = LandedCost::query()->whereNotNull('bill_id')->with('allocations')->first();
    expect($landedCost)->not->toBeNull();
    expect((float) $landedCost->amount)->toBe(40.0);
    expect($landedCost->journal_id)->not->toBeNull();

    $layer = StockLayer::withoutGlobalScopes()
        ->where('product_variant_id', $this->variant->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();

    expect((float) $layer->base_unit_cost)->toBe(25.0);
    expect((float) $layer->landed_unit_cost)->toBe(10.0);
    expect((float) $layer->unit_cost)->toBe(35.0);
});

it('rejects landed costs when billing grn lines on a bill', function () {
    grnSeedAccounts($this->company);

    $create = $this->postJson('/api/admin/grn', grnPayload($this));
    $grnId = $create->json('data.id');
    $grnItemId = $create->json('data.grn_items.0.id');
    $this->postJson("/api/admin/grn/{$grnId}/approve")->assertSuccessful();

    $this->postJson('/api/admin/bill', [
        'bill_date' => now()->toDateString(),
        'party_id' => $this->supplier->id,
        'status' => StatusEnum::DRAFT->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'items' => [
            [
                'product_variant_id' => $this->variant->id,
                'grn_item_id' => $grnItemId,
                'warehouse_id' => $this->warehouse->id,
                'quantity' => 2,
                'rate' => 50,
                'line_discount_type' => 'fixed',
                'line_discount_value' => 0,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'tax_line_type' => 'taxable',
            ],
        ],
        'landed_costs' => [
            [
                'cost_type' => 'transport',
                'treatment' => 'capitalized',
                'amount' => 10,
            ],
        ],
    ])->assertUnprocessable();
});
