<?php

use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use App\Models\AccountGroup;
use Laravel\Sanctum\Sanctum;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Enums\PaymentTermsEnum;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function creditWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

function makeCreditInvoicePayload(object $test, float $rate = 1000): array
{
    return [
        'party_id' => $test->party->id,
        'invoice_date' => now()->toDateString(),
        'status' => 'draft',
        'items' => [
            [
                'product_variant_id' => $test->variant->id,
                'quantity' => 1,
                'rate' => $rate,
                'tax_id' => null,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'tax_line_type' => 'taxable',
            ],
        ],
        'charges' => [],
    ];
}

beforeEach(function () {
    creditWarmCache();
    TenantService::setCompanyId(null);
    TenantService::setBranchId(null);

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026-CRDLMT', 'year_code' => '26CRD',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Credit Limit Test Co',
        'code' => 'CRDCO-'.uniqid(),
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Credit Tester',
        'email' => 'crd-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Credit Customer',
        'code' => 'CRDCUST-'.uniqid(),
        'type' => PartyTypeEnum::CUSTOMER,
        'credit_limit' => 5000,
        'payment_terms' => PaymentTermsEnum::Net30->value,
        'credit_days' => 30,
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Credit Product',
        'code' => 'CRDPRD-'.uniqid(),
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'CRDSKU-'.uniqid(),
        'sales_price' => 1000,
        'is_default' => true,
    ]);

    $group = AccountGroup::create([
        'company_id' => $this->company->id, 'name' => 'CRD GL', 'code' => 'CRDGL-'.uniqid(),
    ]);
    $this->arAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => $group->id,
        'name' => 'AR CRD', 'code' => 'AR-CRD-'.uniqid(), 'is_active' => true,
    ]);
    $this->salesAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => $group->id,
        'name' => 'Sales CRD', 'code' => 'SAL-CRD-'.uniqid(), 'is_active' => true,
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'ar_account_id' => $this->arAccount->id,
        'sales_account_id' => $this->salesAccount->id,
    ]);

    TenantService::setCompanyId($this->company->id);
    Sanctum::actingAs($this->user, ['*'], 'admin');
});

it('stores and returns payment terms fields on party', function () {
    $party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Terms Customer',
        'code' => 'TRMCUST-'.uniqid(),
        'type' => PartyTypeEnum::CUSTOMER,
        'credit_limit' => 10000,
        'payment_terms' => PaymentTermsEnum::Net60->value,
        'credit_days' => 60,
        'custom_days' => null,
    ]);

    $response = $this->getJson('/api/admin/party/'.$party->id);

    $response->assertSuccessful();
    $data = $response->json('data');
    expect($data['payment_terms'])->toBe('net60')
        ->and($data['payment_terms_label'])->toBe('Net 60 Days')
        ->and($data['credit_days'])->toBe(60)
        ->and((float) $data['credit_limit'])->toEqual(10000.0);
});

it('allows invoice creation within credit limit', function () {
    $response = $this->postJson('/api/admin/invoice', makeCreditInvoicePayload($this, 3000));

    $response->assertSuccessful();
    expect(Invoice::count())->toBe(1);
});

it('blocks invoice creation when credit limit is exceeded', function () {
    Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'invoice_no' => 'INV-EXISTING-001',
        'invoice_date' => now()->toDateString(),
        'status' => StatusEnum::APPROVED->value,
        'approved_at' => now(),
        'approve_user_id' => $this->user->id,
        'create_user_id' => $this->user->id,
    ])->invoiceItems()->create([
        'product_variant_id' => $this->variant->id,
        'quantity' => 4,
        'rate' => 1000,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'tax_line_type' => 'taxable',
    ]);

    // 4,000 outstanding + 2,000 new invoice = 6,000 > 5,000 credit limit
    $response = $this->postJson('/api/admin/invoice', makeCreditInvoicePayload($this, 2000));

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['party_id']);
});

it('allows invoice creation when party has no credit limit', function () {
    $this->party->update(['credit_limit' => 0]);

    $response = $this->postJson('/api/admin/invoice', makeCreditInvoicePayload($this, 999999));

    $response->assertSuccessful();
});

it('allows custom payment terms with custom_days', function () {
    $party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Custom Terms Customer',
        'code' => 'CUSTTRM-'.uniqid(),
        'type' => PartyTypeEnum::CUSTOMER,
        'credit_limit' => 0,
        'payment_terms' => PaymentTermsEnum::Custom->value,
        'credit_days' => 45,
        'custom_days' => 45,
    ]);

    $response = $this->getJson('/api/admin/party/'.$party->id);
    $response->assertSuccessful();
    $data = $response->json('data');
    expect($data['payment_terms'])->toBe('custom')
        ->and($data['custom_days'])->toBe(45);
});
