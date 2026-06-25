<?php

use App\Models\Tax;
use App\Models\Bill;
use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Journal;
use App\Models\Product;
use App\Models\BillItem;
use App\Models\TaxGroup;
use App\Enums\StatusEnum;
use App\Models\Warehouse;
use App\Enums\TaxTypeEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\DebitNoteItem;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Database\Seeders\TaxSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function bgtWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
}

function bgtConfigureAccounts(object $test): Account
{
    $account = Account::create([
        'company_id' => $test->company->id,
        'account_group_id' => null,
        'name' => 'Control Account',
        'code' => 'CTRL-BGT',
    ]);

    AccountSetting::create([
        'company_id' => $test->company->id,
        'purchase_account_id' => $account->id,
        'supplier_account_id' => $account->id,
        'vat_account_id' => $account->id,
    ]);

    return $account;
}

function bgtBillPayload(object $test, array $itemOverrides = []): array
{
    return [
        'bill_date' => '2024-08-01',
        'due_date' => null,
        'party_id' => $test->supplier->id,
        'remarks' => 'Test bill',
        'status' => StatusEnum::DRAFT->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => '0',
        'items' => [
            array_merge([
                'product_variant_id' => $test->variant->id,
                'warehouse_id' => $test->warehouse->id,
                'unit_id' => null,
                'quantity' => 1,
                'rate' => 1000,
                'tax_id' => null,
                'tax_group_id' => null,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'line_discount_type' => 'fixed',
                'line_discount_value' => 0,
                'tax_line_type' => 'taxable',
            ], $itemOverrides),
        ],
    ];
}

beforeEach(function () {
    bgtWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2081/82',
        'year_code' => '8182',
        'start_date' => '2024-07-16',
        'end_date' => '2025-07-15',
    ]);

    $this->company = Company::create([
        'company_name' => 'BGT Test Co',
        'code' => 'BGTTC',
        'fiscal_year_id' => $this->fiscalYear->id,
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'BGT Tester',
        'email' => 'bgtest-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'BGT Warehouse',
        'code' => 'BGT-W',
    ]);

    $this->supplier = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Test Supplier',
        'code' => 'SUP-BGT',
        'type' => PartyTypeEnum::SUPPLIER,
    ]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Test Item',
        'code' => 'TI-'.uniqid(),
        'has_variants' => false,
        'is_purchasable' => true,
        'product_type' => 'product',
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'variant_name' => 'Test Item',
        'purchase_price' => 1000,
        'sales_price' => 1200,
        'is_default' => true,
    ]);

    TaxSeeder::seedForCompany($this->company->id);
    TaxSeeder::seedGroupsForCompany($this->company->id);

    $this->vatGroup = TaxGroup::where('company_id', $this->company->id)->where('name', 'VAT 13%')->first();
    $this->vatTax = Tax::where('company_id', $this->company->id)->where('type', TaxTypeEnum::VAT_STANDARD)->first();

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

// ────────────────────────────────────────────
// Schema
// ────────────────────────────────────────────

it('bill_items table has tax_group_id column', function () {
    expect(Schema::getColumnListing('bill_items'))->toContain('tax_group_id');
});

it('debit_note_items table has tax_group_id column', function () {
    expect(Schema::getColumnListing('debit_note_items'))->toContain('tax_group_id');
});

// ────────────────────────────────────────────
// Bill — create with tax group
// ────────────────────────────────────────────

it('saves tax_group_id on bill item when provided', function () {
    $payload = bgtBillPayload($this, ['tax_group_id' => $this->vatGroup->id, 'tax_amount' => 0]);

    $response = $this->postJson('/api/admin/bill', $payload);

    $response->assertCreated();
    $item = BillItem::where('bill_id', $response->json('data.id'))->first();
    expect($item->tax_group_id)->toBe($this->vatGroup->id);
});

it('recalculates tax_amount server-side from tax group on bill item', function () {
    $payload = bgtBillPayload($this, [
        'tax_group_id' => $this->vatGroup->id,
        'tax_amount' => 0,
        'rate' => 1000,
        'quantity' => 1,
    ]);

    $response = $this->postJson('/api/admin/bill', $payload);

    $response->assertCreated();
    $item = BillItem::where('bill_id', $response->json('data.id'))->first();
    expect((float) $item->tax_amount)->toBe(130.0);
});

it('validates tax_group_id must exist in tax_groups', function () {
    $payload = bgtBillPayload($this, ['tax_group_id' => 99999]);

    $this->postJson('/api/admin/bill', $payload)->assertUnprocessable();
});

it('saves individual tax_id with no group on bill item', function () {
    $payload = bgtBillPayload($this, ['tax_id' => $this->vatTax->id, 'tax_amount' => 130]);

    $response = $this->postJson('/api/admin/bill', $payload);

    $response->assertCreated();
    $item = BillItem::where('bill_id', $response->json('data.id'))->first();
    expect($item->tax_id)->toBe($this->vatTax->id);
    expect($item->tax_group_id)->toBeNull();
});

// ────────────────────────────────────────────
// Bill — GL posting with tax group
// ────────────────────────────────────────────

it('posts per-tax GL debit lines when bill item uses a tax group', function () {
    bgtConfigureAccounts($this);

    $approver = User::create([
        'company_id' => $this->company->id,
        'name' => 'Approver',
        'email' => 'approver-bgt-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);
    Sanctum::actingAs($approver, ['*'], 'admin');

    $bill = Bill::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->supplier->id,
        'bill_no' => 'BILL-GL-'.uniqid(),
        'bill_date' => '2024-08-01',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    BillItem::create([
        'bill_id' => $bill->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 1,
        'rate' => 1000,
        'discount_amount' => 0,
        'tax_group_id' => $this->vatGroup->id,
        'tax_amount' => 130,
        'tax_line_type' => 'taxable',
    ]);

    $this->postJson("/api/admin/bill/{$bill->id}/approve")->assertSuccessful();

    $journal = Journal::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('reference_id', $bill->id)
        ->first();

    expect($journal)->not->toBeNull();

    $taxDebitItems = $journal->journalItems()
        ->where('dr_amount', '>', 0)
        ->where('remarks', 'like', '%Input VAT%')
        ->get();

    expect($taxDebitItems)->not->toBeEmpty();
    expect((float) $taxDebitItems->sum('dr_amount'))->toBe(130.0);
});

// ────────────────────────────────────────────
// Debit Note — tax group
// ────────────────────────────────────────────

it('saves tax_group_id and recalculates tax on debit note item', function () {
    $payload = [
        'debit_note_date' => '2024-08-01',
        'party_id' => $this->supplier->id,
        'remarks' => 'Test return',
        'status' => StatusEnum::DRAFT->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => '0',
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'unit_id' => null,
            'quantity' => 1,
            'rate' => 1000,
            'tax_id' => null,
            'tax_group_id' => $this->vatGroup->id,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
        ]],
    ];

    $response = $this->postJson('/api/admin/debit-note', $payload);

    $response->assertCreated();
    $item = DebitNoteItem::where('debit_note_id', $response->json('data.id'))->first();
    expect($item->tax_group_id)->toBe($this->vatGroup->id);
    expect((float) $item->tax_amount)->toBe(130.0);
});
