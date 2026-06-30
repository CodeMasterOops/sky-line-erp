<?php

use App\Models\Tax;
use App\Models\Bill;
use App\Models\User;
use App\Models\Account;
use App\Models\Company;
use App\Models\Product;
use App\Models\BillItem;
use App\Models\TaxGroup;
use App\Enums\StatusEnum;
use App\Models\Warehouse;
use App\Enums\TaxTypeEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\InvoiceItem;
use Laravel\Sanctum\Sanctum;
use App\Enums\ChangeTypeEnum;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Database\Seeders\TaxSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use App\Services\Inventory\InventoryCostCalculator;
use App\Services\Inventory\InventoryLayerReceiptService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function tg5WarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
}

function tg5SeedStock(object $test, int $quantity = 10): void
{
    tg5WarmCache();

    $bill = Bill::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'bill_no' => 'BILL-TG5-'.uniqid(),
        'bill_date' => '2024-08-01',
        'create_user_id' => $test->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $item = BillItem::create([
        'bill_id' => $bill->id,
        'product_variant_id' => $test->variant->id,
        'warehouse_id' => $test->warehouse->id,
        'quantity' => $quantity,
        'rate' => 50,
        'discount_amount' => 0,
    ]);

    $receipt = app(InventoryLayerReceiptService::class);

    DB::transaction(function () use ($receipt, $test, $bill, $item, $quantity) {
        $receipt->receive(
            $test->company,
            $bill,
            $test->variant->id,
            $test->warehouse->id,
            $quantity,
            InventoryCostCalculator::unitCostFromBillItem($item),
            ChangeTypeEnum::PURCHASE,
            $test->user->id,
            null,
            $item->id,
        );
    });
}

function tg5ConfigureAccounts(object $test): Account
{
    $account = Account::create([
        'company_id' => $test->company->id,
        'account_group_id' => null,
        'name' => 'TG5 Control',
        'code' => 'CTRL-TG5',
    ]);

    AccountSetting::create([
        'company_id' => $test->company->id,
        'cash_sales_account_id' => $account->id,
        'customer_account_id' => $account->id,
        'sales_account_id' => $account->id,
        'vat_account_id' => $account->id,
    ]);

    return $account;
}

beforeEach(function () {
    tg5WarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2081/82',
        'year_code' => '8182',
        'start_date' => '2024-07-16',
        'end_date' => '2025-07-15',
    ]);

    $this->company = Company::create([
        'company_name' => 'TG5 Test Co',
        'code' => 'TG5TC',
        'fiscal_year_id' => $this->fiscalYear->id,
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'TG5 Tester',
        'email' => 'tg5test-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'TG5 Warehouse',
        'code' => 'TG5-W',
    ]);

    TaxSeeder::seedForCompany($this->company->id);
    TaxSeeder::seedGroupsForCompany($this->company->id);

    $this->vatGroup = TaxGroup::where('company_id', $this->company->id)->where('name', 'VAT 13%')->first();
    $this->vatTax = Tax::where('company_id', $this->company->id)->where('type', TaxTypeEnum::VAT_STANDARD)->first();

    $product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'TG5 Widget',
        'code' => 'TG5-'.uniqid(),
        'has_variants' => false,
        'is_purchasable' => true,
        'is_saleable' => true,
        'product_type' => 'product',
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'variant_name' => 'TG5 Widget',
        'sku' => 'TG5-SKU-'.uniqid(),
        'purchase_price' => 800,
        'sales_price' => 1000,
        'is_default' => true,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

// ─── pos/products includes tax_id and tax_group_id ───────────────────────────

it('pos/products returns tax_id for a product with an individual tax', function () {
    $product = Product::where('company_id', $this->company->id)->first();
    $product->update(['tax_id' => $this->vatTax->id]);

    $response = $this->getJson('/api/admin/pos/products');

    $response->assertSuccessful();
    $items = $response->json('data');
    $matched = collect($items)->firstWhere('sku', $this->variant->sku);
    expect($matched['tax_id'])->toBe($this->vatTax->id);
    expect($matched['tax_group_id'])->toBeNull();
});

it('pos/products returns tax_group_id for a product with a tax group', function () {
    $product = Product::where('company_id', $this->company->id)->first();
    $product->update(['tax_group_id' => $this->vatGroup->id]);

    $response = $this->getJson('/api/admin/pos/products');

    $response->assertSuccessful();
    $items = $response->json('data');
    $matched = collect($items)->firstWhere('sku', $this->variant->sku);
    expect($matched['tax_group_id'])->toBe($this->vatGroup->id);
    expect($matched['tax_id'])->toBeNull();
});

// ─── POS checkout persists tax_group_id ──────────────────────────────────────

it('pos/checkout persists tax_group_id on invoice item', function () {
    tg5SeedStock($this, 5);
    tg5ConfigureAccounts($this);

    $response = $this->postJson('/api/admin/pos/checkout', [
        'payment_method' => 'cash',
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 1,
            'rate' => 1000,
            'tax_id' => null,
            'tax_group_id' => $this->vatGroup->id,
            'tax_amount' => 130,
            'discount_amount' => 0,
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
        ]],
    ]);

    $response->assertCreated();
    $invoiceId = $response->json('data.id');
    $item = InvoiceItem::where('invoice_id', $invoiceId)->first();
    expect($item->tax_group_id)->toBe($this->vatGroup->id);
    expect((float) $item->tax_amount)->toBe(130.0);
});

it('pos/checkout with individual tax_id and no tax_group_id persists correctly', function () {
    tg5SeedStock($this, 5);
    tg5ConfigureAccounts($this);

    $response = $this->postJson('/api/admin/pos/checkout', [
        'payment_method' => 'cash',
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 1,
            'rate' => 1000,
            'tax_id' => $this->vatTax->id,
            'tax_group_id' => null,
            'tax_amount' => 130,
            'discount_amount' => 0,
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
        ]],
    ]);

    $response->assertCreated();
    $invoiceId = $response->json('data.id');
    $item = InvoiceItem::where('invoice_id', $invoiceId)->first();
    expect($item->tax_id)->toBe($this->vatTax->id);
    expect($item->tax_group_id)->toBeNull();
});

// ─── Validation ──────────────────────────────────────────────────────────────

it('pos/checkout rejects invalid tax_group_id', function () {
    $response = $this->postJson('/api/admin/pos/checkout', [
        'payment_method' => 'cash',
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 1,
            'rate' => 1000,
            'tax_group_id' => 99999,
            'tax_amount' => 0,
            'discount_amount' => 0,
        ]],
    ]);

    $response->assertUnprocessable();
});
