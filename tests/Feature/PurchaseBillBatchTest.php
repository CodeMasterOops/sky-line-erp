<?php

use App\Models\User;
use App\Models\Batch;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\GrnItem;
use App\Models\Product;
use App\Enums\StatusEnum;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Models\StockLayer;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function billBatchWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
}

function billBatchSeedAccounts(Company $company): void
{
    $make = fn (string $name, string $code) => Account::create([
        'company_id' => $company->id,
        'account_group_id' => null,
        'name' => $name,
        'code' => $code,
    ]);

    AccountSetting::create([
        'company_id' => $company->id,
        'inventory_account_id' => $make('Inventory', 'INV-BB')->id,
        'purchase_account_id' => $make('Purchase', 'PUR-BB')->id,
        'grni_account_id' => $make('GRNI', 'GRNI-BB')->id,
        'supplier_account_id' => $make('Suppliers', 'AP-BB')->id,
        'vat_account_id' => $make('VAT', 'VAT-BB')->id,
    ]);
}

function billBatchVariant(object $test, bool $batchTracked): ProductVariant
{
    return ProductVariant::create([
        'company_id' => $test->company->id,
        'product_id' => $test->product->id,
        'sku' => 'SKU-BB-'.uniqid(),
        'sales_price' => 100,
        'purchase_price' => 50,
        'is_batch_tracked' => $batchTracked,
        'is_default' => false,
    ]);
}

function billBatchPayload(object $test, array $itemOverrides, string $status = StatusEnum::APPROVED->value): array
{
    return [
        'bill_date' => '2026-06-15',
        'party_id' => $test->supplier->id,
        'status' => $status,
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'items' => [array_merge([
            'warehouse_id' => $test->warehouse->id,
            'quantity' => 5,
            'rate' => 50,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'tax_line_type' => 'taxable',
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
        ], $itemOverrides)],
    ];
}

beforeEach(function () {
    billBatchWarmCache();
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
        'company_name' => 'BB Test Co',
        'code' => 'BBTC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'BB Tester',
        'email' => 'bb-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'BB Warehouse',
        'code' => 'BB-W',
    ]);

    $this->supplier = Party::create([
        'company_id' => $this->company->id,
        'name' => 'BB Supplier',
        'code' => 'BB-SUP-'.uniqid(),
        'type' => PartyTypeEnum::SUPPLIER,
        'pan' => '123456789',
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'BB Widget',
        'code' => 'BB-PROD-'.uniqid(),
    ]);

    billBatchSeedAccounts($this->company);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

it('creates a single batch from a direct bill new batch_no with correct quantities', function () {
    $variant = billBatchVariant($this, batchTracked: true);

    $response = $this->postJson('/api/admin/bill', billBatchPayload($this, [
        'product_variant_id' => $variant->id,
        'quantity' => 5,
        'batch_no' => 'BILL-B1',
        'expiry_date' => now()->addYear()->toDateString(),
    ]));

    $response->assertCreated();

    $batches = Batch::withoutGlobalScopes()
        ->where('product_variant_id', $variant->id)
        ->where('batch_no', 'BILL-B1')
        ->get();

    expect($batches)->toHaveCount(1);
    expect((float) $batches->first()->initial_qty)->toBe(5.0);
    expect((float) $batches->first()->remaining_qty)->toBe(5.0);

    $layer = StockLayer::withoutGlobalScopes()
        ->where('batch_id', $batches->first()->id)
        ->first();

    expect($layer)->not->toBeNull();
    expect((float) $layer->qty_remaining)->toBe(5.0);
});

it('receives into an existing batch by batch_id without creating a duplicate or double counting', function () {
    $variant = billBatchVariant($this, batchTracked: true);

    $existing = Batch::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $variant->id,
        'warehouse_id' => $this->warehouse->id,
        'batch_no' => 'EXIST-BB',
        'initial_qty' => 0,
        'remaining_qty' => 0,
        'unit_cost' => 50,
        'status' => \App\Enums\BatchStatusEnum::Active,
    ]);

    $this->postJson('/api/admin/bill', billBatchPayload($this, [
        'product_variant_id' => $variant->id,
        'quantity' => 7,
        'batch_id' => $existing->id,
    ]))->assertCreated();

    $batches = Batch::withoutGlobalScopes()
        ->where('product_variant_id', $variant->id)
        ->get();

    expect($batches)->toHaveCount(1);
    expect($batches->first()->id)->toBe($existing->id);
    expect((float) $existing->fresh()->initial_qty)->toBe(7.0);
    expect((float) $existing->fresh()->remaining_qty)->toBe(7.0);
});

it('rejects a direct batch-tracked bill line with no batch selected', function () {
    $variant = billBatchVariant($this, batchTracked: true);

    $this->postJson('/api/admin/bill', billBatchPayload($this, [
        'product_variant_id' => $variant->id,
        'quantity' => 5,
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.batch_id']);
});

it('receives a non-batch-tracked direct bill without any batch', function () {
    $variant = billBatchVariant($this, batchTracked: false);

    $this->postJson('/api/admin/bill', billBatchPayload($this, [
        'product_variant_id' => $variant->id,
        'quantity' => 5,
    ]))->assertCreated();

    expect(Batch::withoutGlobalScopes()->where('product_variant_id', $variant->id)->count())->toBe(0);

    $layer = StockLayer::withoutGlobalScopes()
        ->where('product_variant_id', $variant->id)
        ->first();

    expect($layer)->not->toBeNull();
    expect($layer->batch_id)->toBeNull();
});

it('allows billing a grn-sourced batch-tracked line without a batch on the bill', function () {
    $variant = billBatchVariant($this, batchTracked: true);

    $grn = $this->postJson('/api/admin/grn', [
        'party_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'received_date' => now()->toDateString(),
        'items' => [[
            'product_variant_id' => $variant->id,
            'ordered_qty' => 0,
            'received_qty' => 5,
            'unit_cost' => 50,
            'batch_no' => 'GRN-BB1',
            'expiry_date' => now()->addYear()->toDateString(),
        ]],
    ]);
    $grn->assertCreated();
    $grnId = $grn->json('data.id');
    $this->postJson("/api/admin/grn/{$grnId}/approve")->assertSuccessful();

    $grnItem = GrnItem::withoutGlobalScopes()->where('goods_received_note_id', $grnId)->firstOrFail();
    $batchCountAfterGrn = Batch::withoutGlobalScopes()->where('product_variant_id', $variant->id)->count();

    $this->postJson('/api/admin/bill', billBatchPayload($this, [
        'product_variant_id' => $variant->id,
        'quantity' => 5,
        'grn_item_id' => $grnItem->id,
    ]))->assertCreated();

    // GRN-sourced line is financial only: no second batch, no second receipt.
    expect(Batch::withoutGlobalScopes()->where('product_variant_id', $variant->id)->count())
        ->toBe($batchCountAfterGrn);
});
