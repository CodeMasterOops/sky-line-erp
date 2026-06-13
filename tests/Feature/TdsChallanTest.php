<?php

use App\Models\Tax;
use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Enums\StatusEnum;
use App\Enums\TaxTypeEnum;
use App\Models\FiscalYear;
use App\Models\TdsChallan;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use App\Models\AccountGroup;
use App\Models\TdsDeduction;
use Laravel\Sanctum\Sanctum;
use App\Enums\TdsCategoryEnum;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function tdsChallanWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

function tdsChallanSetup(object $test): array
{
    $group = AccountGroup::create([
        'company_id' => $test->company->id,
        'name' => 'GL-Challan-'.uniqid(),
        'code' => 'GLCH-'.uniqid(),
    ]);

    $ar = Account::create([
        'company_id' => $test->company->id, 'account_group_id' => $group->id,
        'name' => 'AR Challan', 'code' => 'ARCH-'.uniqid(), 'is_active' => true,
    ]);
    $sales = Account::create([
        'company_id' => $test->company->id, 'account_group_id' => $group->id,
        'name' => 'Sales Challan', 'code' => 'SALCH-'.uniqid(), 'is_active' => true,
    ]);
    $cash = Account::create([
        'company_id' => $test->company->id, 'account_group_id' => $group->id,
        'name' => 'Cash Challan', 'code' => 'CASHCH-'.uniqid(), 'is_active' => true,
    ]);
    $tdsReceivable = Account::create([
        'company_id' => $test->company->id, 'account_group_id' => $group->id,
        'name' => 'TDS Rcv Challan', 'code' => 'TDSRCVCH-'.uniqid(), 'is_active' => true,
    ]);

    AccountSetting::create([
        'company_id' => $test->company->id,
        'customer_account_id' => $ar->id,
        'sales_account_id' => $sales->id,
        'cash_sales_account_id' => $sales->id,
        'bank_sales_account_id' => $sales->id,
        'tds_receivable_account_id' => $tdsReceivable->id,
    ]);

    return ['cash' => $cash];
}

/**
 * Creates an approved receipt with TDS and returns the TdsDeduction record.
 */
function tdsChallanCreateDeduction(object $test, Account $cash): TdsDeduction
{
    $invoice = Invoice::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'party_id' => $test->party->id,
        'invoice_no' => 'INV-CHAL-'.uniqid(),
        'invoice_date' => '2026-06-13',
        'create_user_id' => $test->user->id,
        'status' => StatusEnum::APPROVED,
    ]);
    $invoice->invoiceItems()->create([
        'product_variant_id' => $test->variant->id,
        'quantity' => 1,
        'rate' => 10000,
        'tax_amount' => 0,
        'discount_amount' => 0,
    ]);

    $test->postJson('/api/admin/receipt', [
        'party_id' => $test->party->id,
        'receipt_date' => '2026-06-01',
        'payment_method' => 'cash',
        'account_id' => $cash->id,
        'status' => StatusEnum::APPROVED->value,
        'allocations' => [[
            'invoice_id' => $invoice->id,
            'amount' => 10000,
            'tds_id' => $test->tdsTax->id,
            'tds_deducted' => 150,
        ]],
    ])->assertCreated();

    return TdsDeduction::withoutGlobalScopes()->where('party_id', $test->party->id)->latest()->first();
}

beforeEach(function () {
    tdsChallanWarmCache();
    TenantService::setCompanyId(null);
    TenantService::setBranchId(null);

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026-CHLN', 'year_code' => '26CHLN',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Challan Co',
        'code' => 'CHLNCO-'.uniqid(),
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Challan Tester',
        'email' => 'chln-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Challan Party',
        'code' => 'CHLNP-'.uniqid(),
        'type' => PartyTypeEnum::CUSTOMER,
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Challan Product',
        'code' => 'CHLNPROD-'.uniqid(),
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'CHLNSKU-'.uniqid(),
        'sales_price' => 10000,
        'is_default' => true,
    ]);

    $this->tdsTax = Tax::create([
        'company_id' => $this->company->id,
        'name' => 'TDS Challan 1.5%',
        'rate' => 1.5,
        'type' => TaxTypeEnum::TDS->value,
        'tds_category' => TdsCategoryEnum::SERVICE_VAT_BILL->value,
    ]);

    TenantService::setCompanyId($this->company->id);
    Sanctum::actingAs($this->user, ['*'], 'admin');
});

it('creates a TDS challan from deduction records and returns correct total', function () {
    ['cash' => $cash] = tdsChallanSetup($this);
    $deduction = tdsChallanCreateDeduction($this, $cash);

    $payload = [
        'challan_date' => '2026-06-13',
        'party_id' => $this->party->id,
        'period_month' => 6,
        'bank_name' => 'NIC Asia',
        'payment_date' => '2026-06-13',
        'deduction_ids' => [$deduction->id],
    ];

    $response = $this->postJson('/api/admin/tds-challan', $payload);

    $response->assertCreated();
    $data = $response->json('data');
    expect((float) $data['total_tds_amount'])->toBe(150.0);
    expect($data['items'])->toHaveCount(1);
    expect($data['status'])->toBe('pending');
});

it('marks a TDS challan as submitted', function () {
    ['cash' => $cash] = tdsChallanSetup($this);
    $deduction = tdsChallanCreateDeduction($this, $cash);

    $challanId = $this->postJson('/api/admin/tds-challan', [
        'challan_date' => '2026-06-13',
        'party_id' => $this->party->id,
        'period_month' => 6,
        'deduction_ids' => [$deduction->id],
    ])->json('data.id');

    $response = $this->postJson("/api/admin/tds-challan/{$challanId}/submit");

    $response->assertOk();
    expect($response->json('data.status'))->toBe('submitted');
    expect(TdsChallan::find($challanId)->status->value)->toBe('submitted');
});

it('rejects submitting an already submitted challan', function () {
    ['cash' => $cash] = tdsChallanSetup($this);
    $deduction = tdsChallanCreateDeduction($this, $cash);

    $challanId = $this->postJson('/api/admin/tds-challan', [
        'challan_date' => '2026-06-13',
        'party_id' => $this->party->id,
        'period_month' => 6,
        'deduction_ids' => [$deduction->id],
    ])->json('data.id');

    $this->postJson("/api/admin/tds-challan/{$challanId}/submit")->assertOk();
    $this->postJson("/api/admin/tds-challan/{$challanId}/submit")->assertUnprocessable();
});

it('lists TDS challans with party filter', function () {
    ['cash' => $cash] = tdsChallanSetup($this);
    $deduction = tdsChallanCreateDeduction($this, $cash);

    $this->postJson('/api/admin/tds-challan', [
        'challan_date' => '2026-06-13',
        'party_id' => $this->party->id,
        'period_month' => 6,
        'deduction_ids' => [$deduction->id],
    ])->assertCreated();

    $response = $this->getJson('/api/admin/tds-challan?party_id='.$this->party->id);

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('lists unbundled TDS deductions for a party and month', function () {
    ['cash' => $cash] = tdsChallanSetup($this);
    $deduction = tdsChallanCreateDeduction($this, $cash);

    $response = $this->getJson("/api/admin/tds-deductions?party_id={$this->party->id}&period_month=6&unbundled=1");

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->not->toBeEmpty();
    $row = $data[0];
    expect($row)->toHaveKeys(['id', 'tds_category', 'tds_category_label', 'base_amount', 'tds_amount']);
    expect($row['id'])->toBe($deduction->id);
    expect((float) $row['tds_amount'])->toBe(150.0);
});

it('excludes deductions already bundled in a challan from unbundled list', function () {
    ['cash' => $cash] = tdsChallanSetup($this);
    $deduction = tdsChallanCreateDeduction($this, $cash);

    $this->postJson('/api/admin/tds-challan', [
        'challan_date' => '2026-06-13',
        'party_id' => $this->party->id,
        'period_month' => 6,
        'deduction_ids' => [$deduction->id],
    ])->assertCreated();

    $response = $this->getJson("/api/admin/tds-deductions?party_id={$this->party->id}&period_month=6&unbundled=1");

    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
});
