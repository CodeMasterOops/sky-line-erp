<?php

use App\Models\Bill;
use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Journal;
use App\Models\Product;
use App\Enums\StatusEnum;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\PurchaseOrder;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function billTestWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

function billTestSeedAccounts(Company $company): AccountSetting
{
    $purchase = Account::create(['company_id' => $company->id, 'account_group_id' => null, 'name' => 'Purchase', 'code' => 'PUR-BT-'.uniqid()]);
    $supplier = Account::create(['company_id' => $company->id, 'account_group_id' => null, 'name' => 'Supplier AP', 'code' => 'AP-BT-'.uniqid()]);
    $vat = Account::create(['company_id' => $company->id, 'account_group_id' => null, 'name' => 'VAT In', 'code' => 'VATIN-BT-'.uniqid()]);

    return AccountSetting::create([
        'company_id' => $company->id,
        'purchase_account_id' => $purchase->id,
        'supplier_account_id' => $supplier->id,
        'vat_account_id' => $vat->id,
    ]);
}

function billTestPayload(object $test, array $overrides = []): array
{
    return array_merge([
        'party_id' => $test->supplier->id,
        'bill_date' => '2024-04-14',
        'status' => StatusEnum::DRAFT->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => null,
        'order_discount_amount' => 0,
        'items' => [[
            'product_variant_id' => $test->variant->id,
            'warehouse_id' => $test->warehouse->id,
            'quantity' => 1,
            'rate' => 100,
            'tax_id' => null,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'tax_line_type' => 'taxable',
            'line_discount_type' => 'fixed',
            'line_discount_value' => null,
        ]],
    ], $overrides);
}

beforeEach(function () {
    billTestWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2081', 'year_code' => '81',
        'start_date' => '2024-04-14', 'end_date' => '2025-04-13',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Purchase Bill Test Co',
        'code' => 'PBTC-'.uniqid(),
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Bill Tester',
        'email' => 'bill-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->supplier = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Test Supplier',
        'code' => 'SUP-BT-'.uniqid(),
        'type' => PartyTypeEnum::SUPPLIER,
    ]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Widget',
        'code' => 'WGT-BT-'.uniqid(),
        'product_type' => \App\Enums\ProductTypeEnum::PRODUCT->value,
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'sku' => 'WGT-BT-'.uniqid(),
        'purchase_price' => 100,
        'is_default' => true,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Main',
        'code' => 'WH-BT-'.uniqid(),
    ]);

    $this->accountSetting = billTestSeedAccounts($this->company);

    TenantService::setCompanyId($this->company->id);
    Sanctum::actingAs($this->user, [], 'admin');
});

it('populates bill_date_bs when creating a draft bill', function () {
    $this->postJson('/api/admin/bill', billTestPayload($this))->assertSuccessful();

    $bill = Bill::latest('id')->first();
    expect($bill->bill_date_bs)->not->toBeNull()
        ->and($bill->bill_date_bs)->toStartWith('2081');
});

it('populates bill_date_bs when updating a bill', function () {
    $bill = Bill::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->supplier->id,
        'bill_no' => 'BILL-UPD-'.uniqid(),
        'bill_date' => '2024-04-14',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $this->putJson("/api/admin/bill/{$bill->id}", billTestPayload($this, ['bill_date' => '2024-05-01']))->assertSuccessful();

    expect($bill->fresh()->bill_date_bs)->not->toBeNull()
        ->and($bill->fresh()->bill_date_bs)->toStartWith('2081');
});

it('creates a balanced GL journal when an approved bill is created', function () {
    $this->postJson('/api/admin/bill', billTestPayload($this, ['status' => StatusEnum::APPROVED->value]))->assertSuccessful();

    $bill = Bill::latest('id')->first();
    $journal = Journal::withoutGlobalScopes()->where('reference_id', $bill->id)->first();

    expect($journal)->not->toBeNull();

    $drTotal = $journal->journalItems()->sum('dr_amount');
    $crTotal = $journal->journalItems()->sum('cr_amount');
    expect(round($drTotal, 2))->toBe(round($crTotal, 2));
});

it('voids a bill and soft-deletes its GL journal', function () {
    $this->postJson('/api/admin/bill', billTestPayload($this, ['status' => StatusEnum::APPROVED->value]))->assertSuccessful();

    $bill = Bill::latest('id')->first();
    $journal = Journal::withoutGlobalScopes()->where('reference_id', $bill->id)->first();

    $this->postJson("/api/admin/bill/{$bill->id}/void")->assertSuccessful();

    expect(Journal::withoutGlobalScopes()->find($journal->id)->deleted_at)->not->toBeNull()
        ->and($bill->fresh()->voided_at)->not->toBeNull();
});

it('prevents deleting an approved purchase order', function () {
    $po = PurchaseOrder::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->supplier->id,
        'order_no' => 'PO-APP-'.uniqid(),
        'order_date' => '2024-04-14',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
    ]);

    $this->deleteJson("/api/admin/purchase-order/{$po->id}")->assertStatus(422);
    expect(PurchaseOrder::withTrashed()->find($po->id))->not->toBeNull();
});

it('prevents deleting a purchase order that has linked bills', function () {
    $po = PurchaseOrder::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->supplier->id,
        'order_no' => 'PO-BILL-'.uniqid(),
        'order_date' => '2024-04-14',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    Bill::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->supplier->id,
        'purchase_order_id' => $po->id,
        'bill_no' => 'BILL-PO-'.uniqid(),
        'bill_date' => '2024-04-14',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $this->deleteJson("/api/admin/purchase-order/{$po->id}")->assertStatus(422);
});

it('allows deleting a draft purchase order without linked bills', function () {
    $po = PurchaseOrder::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->supplier->id,
        'order_no' => 'PO-DRAFT-'.uniqid(),
        'order_date' => '2024-04-14',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $this->deleteJson("/api/admin/purchase-order/{$po->id}")->assertSuccessful();
    expect(PurchaseOrder::find($po->id))->toBeNull();
});
