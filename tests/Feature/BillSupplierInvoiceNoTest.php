<?php

use App\Models\Bill;
use App\Models\User;
use App\Models\Party;
use App\Models\Company;
use App\Models\Product;
use App\Enums\StatusEnum;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function bsinWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
}

function bsinBillPayload(object $test, array $overrides = []): array
{
    return array_merge([
        'bill_date' => '2024-08-01',
        'due_date' => null,
        'party_id' => $test->supplier->id,
        'supplier_invoice_no' => 'SUP-INV-1001',
        'remarks' => 'Test bill',
        'status' => StatusEnum::DRAFT->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => '0',
        'items' => [
            [
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
            ],
        ],
    ], $overrides);
}

beforeEach(function () {
    bsinWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2081/82',
        'year_code' => '8182',
        'start_date' => '2024-07-16',
        'end_date' => '2025-07-15',
    ]);

    $this->company = Company::create([
        'company_name' => 'BSIN Test Co',
        'code' => 'BSINTC',
        'fiscal_year_id' => $this->fiscalYear->id,
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'BSIN Tester',
        'email' => 'bsintest-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'BSIN Warehouse',
        'code' => 'BSIN-W',
    ]);

    $this->supplier = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Test Supplier',
        'code' => 'SUP-BSIN',
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

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

it('persists and returns supplier_invoice_no on create', function () {
    $response = $this->postJson('/api/admin/bill', bsinBillPayload($this));

    $response->assertCreated()
        ->assertJsonPath('data.supplier_invoice_no', 'SUP-INV-1001');

    $bill = Bill::find($response->json('data.id'));
    expect($bill->supplier_invoice_no)->toBe('SUP-INV-1001');
});

it('returns supplier_invoice_no on show', function () {
    $create = $this->postJson('/api/admin/bill', bsinBillPayload($this, [
        'supplier_invoice_no' => 'IRD-555',
    ]))->assertCreated();

    $billId = $create->json('data.id');

    $this->getJson("/api/admin/bill/{$billId}")
        ->assertSuccessful()
        ->assertJsonPath('data.supplier_invoice_no', 'IRD-555');
});

it('updates supplier_invoice_no on draft bill', function () {
    $create = $this->postJson('/api/admin/bill', bsinBillPayload($this, [
        'supplier_invoice_no' => 'OLD-001',
    ]))->assertCreated();

    $billId = $create->json('data.id');
    $payload = bsinBillPayload($this, [
        'supplier_invoice_no' => 'NEW-999',
    ]);

    $this->putJson("/api/admin/bill/{$billId}", $payload)
        ->assertSuccessful()
        ->assertJsonPath('data.supplier_invoice_no', 'NEW-999');

    expect(Bill::find($billId)->supplier_invoice_no)->toBe('NEW-999');
});

it('allows nullable supplier_invoice_no', function () {
    $response = $this->postJson('/api/admin/bill', bsinBillPayload($this, [
        'supplier_invoice_no' => null,
    ]));

    $response->assertCreated()
        ->assertJsonPath('data.supplier_invoice_no', '');

    expect(Bill::find($response->json('data.id'))->supplier_invoice_no)->toBeNull();
});
