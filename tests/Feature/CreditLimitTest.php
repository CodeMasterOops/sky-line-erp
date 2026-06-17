<?php

use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Enums\StatusEnum;
use App\Models\Warehouse;
use App\Models\FiscalYear;
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

function clWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
}

function clAccountSetting(object $test): Account
{
    $account = Account::create([
        'company_id' => $test->company->id,
        'account_group_id' => null,
        'name' => 'CL Cash',
        'code' => 'CLCASH-'.uniqid(),
    ]);

    AccountSetting::create([
        'company_id' => $test->company->id,
        'cash_sales_account_id' => $account->id,
        'bank_sales_account_id' => $account->id,
        'customer_account_id' => $account->id,
        'sales_account_id' => $account->id,
    ]);

    return $account;
}

beforeEach(function () {
    clWarmCache();
    TenantService::setCompanyId(null);
    TenantService::setBranchId(null);

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'CL Test Co',
        'code' => 'CLTC-'.uniqid(),
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'CL Tester',
        'email' => 'cl-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'CL Widget',
        'code' => 'CLW-'.uniqid(),
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'CL Main',
        'code' => 'CLWH-'.uniqid(),
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'CLSKU-'.uniqid(),
        'sales_price' => 100,
        'is_default' => true,
        'type' => 'service',
    ]);

    TenantService::setCompanyId($this->company->id);
    Sanctum::actingAs($this->user, ['*'], 'admin');
});

it('allows approving invoice when party has no credit limit set', function () {
    clAccountSetting($this);

    $party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Unlimited Customer',
        'code' => 'UNLTD-'.uniqid(),
        'type' => PartyTypeEnum::CUSTOMER,
        'credit_limit' => 0,
    ]);

    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $party->id,
        'invoice_no' => 'INV-CL-'.uniqid(),
        'invoice_date' => '2026-06-01',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
        'total_amount' => 99999,
        'paid_amount' => 0,
    ]);

    $response = $this->postJson("/api/admin/invoice/{$invoice->id}/approve");

    $response->assertOk();
});

it('allows approving invoice when outstanding balance is within credit limit', function () {
    clAccountSetting($this);

    $party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Limited Customer',
        'code' => 'LTD-'.uniqid(),
        'type' => PartyTypeEnum::CUSTOMER,
        'credit_limit' => 10000,
    ]);

    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $party->id,
        'invoice_no' => 'INV-CL-'.uniqid(),
        'invoice_date' => '2026-06-01',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
        'total_amount' => 5000,
        'paid_amount' => 0,
    ]);

    $response = $this->postJson("/api/admin/invoice/{$invoice->id}/approve");

    $response->assertOk();
});

it('blocks approving invoice when it would exceed the credit limit', function () {
    $party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Capped Customer',
        'code' => 'CAP-'.uniqid(),
        'type' => PartyTypeEnum::CUSTOMER,
        'credit_limit' => 5000,
    ]);

    Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $party->id,
        'invoice_no' => 'INV-CL-EXIST-'.uniqid(),
        'invoice_date' => '2026-05-01',
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED,
        'total_amount' => 4000,
        'paid_amount' => 0,
    ]);

    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $party->id,
        'invoice_no' => 'INV-CL-NEW-'.uniqid(),
        'invoice_date' => '2026-06-01',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
        'total_amount' => 2000,
        'paid_amount' => 0,
    ]);

    $response = $this->postJson("/api/admin/invoice/{$invoice->id}/approve");

    $response->assertStatus(422);
    $message = $response->json('message') ?? $response->json('errors.credit_limit.0');
    expect($message)->toContain('credit limit');
});

it('blocks direct creation of approved invoice when it would exceed the credit limit', function () {
    $party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Direct Cap Customer',
        'code' => 'DCAP-'.uniqid(),
        'type' => PartyTypeEnum::CUSTOMER,
        'credit_limit' => 1000,
    ]);

    $response = $this->postJson('/api/admin/invoice', [
        'invoice_date' => '2026-06-01',
        'party_id' => $party->id,
        'status' => StatusEnum::APPROVED->value,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'quantity' => 15,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
        ]],
    ]);

    $response->assertStatus(422);
    $message = $response->json('message') ?? $response->json('errors.credit_limit.0');
    expect($message)->toContain('credit limit');
});

it('considers paid amounts when checking credit limit', function () {
    clAccountSetting($this);

    $party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Partially Paid Customer',
        'code' => 'PPD-'.uniqid(),
        'type' => PartyTypeEnum::CUSTOMER,
        'credit_limit' => 5000,
    ]);

    Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $party->id,
        'invoice_no' => 'INV-CL-PAID-'.uniqid(),
        'invoice_date' => '2026-05-01',
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED,
        'total_amount' => 4000,
        'paid_amount' => 4000,
    ]);

    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $party->id,
        'invoice_no' => 'INV-CL-NEW2-'.uniqid(),
        'invoice_date' => '2026-06-01',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
        'total_amount' => 4500,
        'paid_amount' => 0,
    ]);

    $response = $this->postJson("/api/admin/invoice/{$invoice->id}/approve");

    $response->assertOk();
});
