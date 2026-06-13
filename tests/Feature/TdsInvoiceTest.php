<?php

use App\Models\Tax;
use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Enums\StatusEnum;
use App\Models\Warehouse;
use App\Enums\TaxTypeEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use App\Models\AccountGroup;
use Laravel\Sanctum\Sanctum;
use App\Enums\TdsCategoryEnum;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function tdsInvWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

function tdsInvSetupAccounts(object $test): void
{
    $group = AccountGroup::create([
        'company_id' => $test->company->id,
        'name' => 'GL-TdsInv-'.uniqid(),
        'code' => 'GLTDSINV-'.uniqid(),
    ]);
    $ar = Account::create([
        'company_id' => $test->company->id, 'account_group_id' => $group->id,
        'name' => 'AR TDS', 'code' => 'AR-TDS-'.uniqid(), 'is_active' => true,
    ]);
    $sales = Account::create([
        'company_id' => $test->company->id, 'account_group_id' => $group->id,
        'name' => 'Sales TDS', 'code' => 'SAL-TDS-'.uniqid(), 'is_active' => true,
    ]);
    AccountSetting::create([
        'company_id' => $test->company->id,
        'customer_account_id' => $ar->id,
        'sales_account_id' => $sales->id,
        'cash_sales_account_id' => $sales->id,
        'bank_sales_account_id' => $sales->id,
    ]);
}

beforeEach(function () {
    tdsInvWarmCache();
    TenantService::setCompanyId(null);
    TenantService::setBranchId(null);

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026-TDSINV', 'year_code' => '26TDSINV',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'TDS Inv Co',
        'code' => 'TDSINV-'.uniqid(),
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'TDS Inv Tester',
        'email' => 'tdsinv-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'TDS Customer',
        'code' => 'TDSCUST-'.uniqid(),
        'type' => PartyTypeEnum::CUSTOMER,
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'TDS Service',
        'code' => 'TDSSVC-'.uniqid(),
        'product_type' => \App\Enums\ProductTypeEnum::SERVICE,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'TDS WH',
        'code' => 'TDSWH-'.uniqid(),
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'TDSSKU-'.uniqid(),
        'sales_price' => 10000,
        'is_default' => true,
    ]);

    $this->tdsTax = Tax::create([
        'company_id' => $this->company->id,
        'name' => 'TDS Service Fee 1.5%',
        'rate' => 1.5,
        'type' => TaxTypeEnum::TDS->value,
        'tds_category' => TdsCategoryEnum::SERVICE_VAT_BILL->value,
    ]);

    TenantService::setCompanyId($this->company->id);
    Sanctum::actingAs($this->user, ['*'], 'admin');
});

it('creates an invoice with TDS-applicable items and saves tds fields correctly', function () {
    tdsInvSetupAccounts($this);

    $payload = [
        'party_id' => $this->party->id,
        'invoice_date' => '2026-06-13',
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'quantity' => 1,
            'rate' => 10000,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'is_tds_applicable' => true,
            'tds_id' => $this->tdsTax->id,
            'tds_base_amount' => 10000,
            'tds_amount' => 150,
        ]],
    ];

    $response = $this->postJson('/api/admin/invoice', $payload);

    $response->assertCreated();
    $item = $response->json('data.items.0');
    expect($item['is_tds_applicable'])->toBeTrue();
    expect($item['tds_id'])->toBe($this->tdsTax->id);
    expect((float) $item['tds_base_amount'])->toBe(10000.0);
    expect((float) $item['tds_amount'])->toBe(150.0);
});

it('does not allow TDS tax to be used as the regular VAT tax_id on invoice items', function () {
    tdsInvSetupAccounts($this);

    $payload = [
        'party_id' => $this->party->id,
        'invoice_date' => '2026-06-13',
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'quantity' => 1,
            'rate' => 10000,
            'tax_id' => $this->tdsTax->id,
            'tax_amount' => 500,
            'discount_amount' => 0,
        ]],
    ];

    $response = $this->postJson('/api/admin/invoice', $payload);

    $response->assertUnprocessable();
    // Laravel returns validation errors with dot notation keys
    $errors = $response->json('errors');
    $hasError = isset($errors['items.0.tax_id']) || isset($errors['items'][0]['tax_id']);
    expect($hasError)->toBeTrue();
});

it('does NOT post a TDS GL journal line when approving a service invoice', function () {
    tdsInvSetupAccounts($this);

    $payload = [
        'party_id' => $this->party->id,
        'invoice_date' => '2026-06-13',
        'status' => StatusEnum::APPROVED->value,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'quantity' => 1,
            'rate' => 10000,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'is_tds_applicable' => true,
            'tds_id' => $this->tdsTax->id,
            'tds_base_amount' => 10000,
            'tds_amount' => 150,
        ]],
    ];

    $response = $this->postJson('/api/admin/invoice', $payload);

    $response->assertCreated();

    $invoiceId = $response->json('data.id');
    $invoice = Invoice::find($invoiceId);
    $journal = $invoice->journal()->withoutGlobalScopes()->first();

    expect($journal)->not->toBeNull();

    // TDS is NOT the seller's liability — journal should have only AR CR and Sales CR
    $journalItems = $journal->journalItems()->get();
    expect($journalItems)->toHaveCount(2);
});

it('rejects a non-TDS tax used as tds_id on invoice items', function () {
    tdsInvSetupAccounts($this);

    $vatTax = Tax::create([
        'company_id' => $this->company->id,
        'name' => 'VAT 13%',
        'rate' => 13,
        'type' => \App\Enums\TaxTypeEnum::VAT_STANDARD->value,
        'is_system' => false,
    ]);

    $payload = [
        'party_id' => $this->party->id,
        'invoice_date' => '2026-06-13',
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'quantity' => 1,
            'rate' => 10000,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'is_tds_applicable' => true,
            'tds_id' => $vatTax->id,
            'tds_base_amount' => 10000,
            'tds_amount' => 1300,
        ]],
    ];

    $response = $this->postJson('/api/admin/invoice', $payload);

    $response->assertUnprocessable();
    $errors = $response->json('errors');
    $hasError = isset($errors['items.0.tds_id']) || isset($errors['items'][0]['tds_id']);
    expect($hasError)->toBeTrue();
});

it('updates TDS fields on invoice items when editing', function () {
    tdsInvSetupAccounts($this);

    $createPayload = [
        'party_id' => $this->party->id,
        'invoice_date' => '2026-06-13',
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'quantity' => 1,
            'rate' => 10000,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'is_tds_applicable' => true,
            'tds_id' => $this->tdsTax->id,
            'tds_base_amount' => 10000,
            'tds_amount' => 150,
        ]],
    ];

    $id = $this->postJson('/api/admin/invoice', $createPayload)->json('data.id');

    $updatePayload = $createPayload;
    $updatePayload['items'][0]['is_tds_applicable'] = false;
    $updatePayload['items'][0]['tds_id'] = null;
    $updatePayload['items'][0]['tds_base_amount'] = 0;
    $updatePayload['items'][0]['tds_amount'] = 0;

    $response = $this->putJson("/api/admin/invoice/{$id}", $updatePayload);

    $response->assertOk();
    $item = $response->json('data.items.0');
    expect($item['is_tds_applicable'])->toBeFalse();
    expect((float) $item['tds_amount'])->toBe(0.0);
});
