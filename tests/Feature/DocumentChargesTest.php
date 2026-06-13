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
use App\Models\AccountGroup;
use Laravel\Sanctum\Sanctum;
use App\Enums\ChargeTypeEnum;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Models\QuotationCharge;
use App\Services\TenantService;
use App\Models\CreditNoteCharge;
use App\Models\SalesOrderCharge;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function dcWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

function dcMakeChargeAccount(int $companyId): Account
{
    $group = AccountGroup::create([
        'company_id' => $companyId,
        'name' => 'Surcharge Income DC-'.uniqid(),
        'code' => 'SCI-'.uniqid(),
    ]);

    return Account::create([
        'company_id' => $companyId,
        'account_group_id' => $group->id,
        'name' => 'Freight Income DC-'.uniqid(),
        'code' => 'FRT-DC-'.uniqid(),
        'is_active' => true,
    ]);
}

function dcAccountSetting(object $test): void
{
    $group = AccountGroup::create([
        'company_id' => $test->company->id,
        'name' => 'GL Accounts DC',
        'code' => 'GLDC-'.uniqid(),
    ]);
    $ar = Account::create([
        'company_id' => $test->company->id, 'account_group_id' => $group->id,
        'name' => 'AR DC', 'code' => 'AR-DC-'.uniqid(), 'is_active' => true,
    ]);
    $sales = Account::create([
        'company_id' => $test->company->id, 'account_group_id' => $group->id,
        'name' => 'Sales DC', 'code' => 'SAL-DC-'.uniqid(), 'is_active' => true,
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
    dcWarmCache();
    TenantService::setCompanyId(null);
    TenantService::setBranchId(null);

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026-DC', 'year_code' => '26DC',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Doc Charge Co',
        'code' => 'DCCO-'.uniqid(),
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'DC Tester',
        'email' => 'dc-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'DC Customer',
        'code' => 'DCCUST-'.uniqid(),
        'type' => PartyTypeEnum::CUSTOMER,
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'DC Widget',
        'code' => 'DCWGT-'.uniqid(),
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'DC WH',
        'code' => 'DCWH-'.uniqid(),
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'DCSKU-'.uniqid(),
        'sales_price' => 100,
        'is_default' => true,
    ]);

    $this->chargeAccount = dcMakeChargeAccount($this->company->id);

    TenantService::setCompanyId($this->company->id);
    Sanctum::actingAs($this->user, ['*'], 'admin');
});

// ─── Enum endpoint ────────────────────────────────────────────────────────────

it('GET enum/charge-types returns all ChargeTypeEnum cases with labels', function () {
    $response = $this->getJson('/api/admin/enum/charge-types');

    $response->assertOk();
    $data = $response->json('data');

    expect($data)->toBeArray()->not->toBeEmpty();
    expect(count($data))->toBe(count(ChargeTypeEnum::cases()));

    $values = array_column($data, 'value');
    foreach (ChargeTypeEnum::cases() as $case) {
        expect($values)->toContain($case->value);
    }

    $item = collect($data)->firstWhere('value', ChargeTypeEnum::Freight->value);
    expect($item)->not->toBeNull();
    expect($item['label'])->toBeString()->not->toBeEmpty();
});

// ─── Quotation charges ────────────────────────────────────────────────────────

it('creates a quotation with charges and returns them in the response', function () {
    $payload = [
        'party_id' => $this->party->id,
        'quotation_date' => now()->toDateString(),
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'quantity' => 2,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
        ]],
        'charges' => [
            [
                'name' => 'Freight',
                'charge_type' => ChargeTypeEnum::Freight->value,
                'account_id' => $this->chargeAccount->id,
                'amount' => 150,
                'tax_amount' => 0,
            ],
        ],
    ];

    $response = $this->postJson('/api/admin/quotation', $payload);

    $response->assertCreated();
    $data = $response->json('data');
    expect($data['charges'])->toHaveCount(1);
    expect((float) $data['charges'][0]['amount'])->toBe(150.0);
});

it('replaces quotation charges on update', function () {
    $createPayload = [
        'party_id' => $this->party->id,
        'quotation_date' => now()->toDateString(),
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'quantity' => 1,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
        ]],
        'charges' => [[
            'name' => 'Freight',
            'charge_type' => ChargeTypeEnum::Freight->value,
            'account_id' => $this->chargeAccount->id,
            'amount' => 100,
            'tax_amount' => 0,
        ]],
    ];

    $id = $this->postJson('/api/admin/quotation', $createPayload)->json('data.id');

    $updatePayload = $createPayload;
    $updatePayload['charges'] = [
        [
            'name' => 'Handling',
            'charge_type' => ChargeTypeEnum::Handling->value,
            'account_id' => $this->chargeAccount->id,
            'amount' => 75,
            'tax_amount' => 0,
        ],
    ];

    $response = $this->putJson("/api/admin/quotation/{$id}", $updatePayload);

    $response->assertOk();
    expect($response->json('data.charges'))->toHaveCount(1);
    expect($response->json('data.charges.0.name'))->toBe('Handling');
    expect(QuotationCharge::where('quotation_id', $id)->where('name', 'Freight')->exists())->toBeFalse();
});

// ─── Sales Order charges ──────────────────────────────────────────────────────

it('creates a sales order with charges and returns them in the response', function () {
    $payload = [
        'party_id' => $this->party->id,
        'order_date' => now()->toDateString(),
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'quantity' => 1,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
        ]],
        'charges' => [
            [
                'name' => 'Insurance',
                'charge_type' => ChargeTypeEnum::Insurance->value,
                'account_id' => $this->chargeAccount->id,
                'amount' => 200,
                'tax_amount' => 0,
            ],
        ],
    ];

    $response = $this->postJson('/api/admin/sales-order', $payload);

    $response->assertCreated();
    $data = $response->json('data');
    expect($data['charges'])->toHaveCount(1);
    expect((float) $data['charges'][0]['amount'])->toBe(200.0);
});

it('replaces sales order charges on update', function () {
    $createPayload = [
        'party_id' => $this->party->id,
        'order_date' => now()->toDateString(),
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'quantity' => 1,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
        ]],
        'charges' => [[
            'name' => 'Packaging',
            'charge_type' => ChargeTypeEnum::Packaging->value,
            'account_id' => $this->chargeAccount->id,
            'amount' => 50,
            'tax_amount' => 0,
        ]],
    ];

    $id = $this->postJson('/api/admin/sales-order', $createPayload)->json('data.id');

    $updatePayload = $createPayload;
    $updatePayload['charges'] = [];

    $response = $this->putJson("/api/admin/sales-order/{$id}", $updatePayload);

    $response->assertOk();
    expect($response->json('data.charges'))->toBeEmpty();
    expect(SalesOrderCharge::where('sales_order_id', $id)->count())->toBe(0);
});

// ─── Credit Note charges ──────────────────────────────────────────────────────

it('creates a credit note with charges and returns them in the response', function () {
    dcAccountSetting($this);

    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'invoice_no' => 'INV-DC-'.uniqid(),
        'invoice_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
    ]);

    $payload = [
        'party_id' => $this->party->id,
        'credit_note_date' => now()->toDateString(),
        'invoice_id' => $invoice->id,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 1,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
        ]],
        'charges' => [
            [
                'name' => 'Loading',
                'charge_type' => ChargeTypeEnum::Loading->value,
                'account_id' => $this->chargeAccount->id,
                'amount' => 80,
                'tax_amount' => 0,
            ],
        ],
    ];

    $response = $this->postJson('/api/admin/credit-note', $payload);

    $response->assertCreated();
    $data = $response->json('data');
    expect($data['charges'])->toHaveCount(1);
    expect((float) $data['charges'][0]['amount'])->toBe(80.0);
});

it('replaces credit note charges on update', function () {
    dcAccountSetting($this);

    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'invoice_no' => 'INV-DCUP-'.uniqid(),
        'invoice_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
    ]);

    $createPayload = [
        'party_id' => $this->party->id,
        'credit_note_date' => now()->toDateString(),
        'invoice_id' => $invoice->id,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 1,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
        ]],
        'charges' => [[
            'name' => 'Other',
            'charge_type' => ChargeTypeEnum::Other->value,
            'account_id' => $this->chargeAccount->id,
            'amount' => 30,
            'tax_amount' => 0,
        ]],
    ];

    $id = $this->postJson('/api/admin/credit-note', $createPayload)->json('data.id');

    $updatePayload = $createPayload;
    $updatePayload['charges'] = [];

    $response = $this->putJson("/api/admin/credit-note/{$id}", $updatePayload);

    $response->assertOk();
    expect($response->json('data.charges'))->toBeEmpty();
    expect(CreditNoteCharge::where('credit_note_id', $id)->count())->toBe(0);
});
