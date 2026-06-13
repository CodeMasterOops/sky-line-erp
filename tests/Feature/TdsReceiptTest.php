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

function tdsRcpWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

function tdsRcpSetup(object $test): array
{
    $group = AccountGroup::create([
        'company_id' => $test->company->id,
        'name' => 'GL-TdsRcp-'.uniqid(),
        'code' => 'GLTDSRCP-'.uniqid(),
    ]);

    $ar = Account::create([
        'company_id' => $test->company->id, 'account_group_id' => $group->id,
        'name' => 'AR TdsRcp', 'code' => 'AR-TDSRCP-'.uniqid(), 'is_active' => true,
    ]);
    $sales = Account::create([
        'company_id' => $test->company->id, 'account_group_id' => $group->id,
        'name' => 'Sales TdsRcp', 'code' => 'SAL-TDSRCP-'.uniqid(), 'is_active' => true,
    ]);
    $cash = Account::create([
        'company_id' => $test->company->id, 'account_group_id' => $group->id,
        'name' => 'Cash TdsRcp', 'code' => 'CASH-TDSRCP-'.uniqid(), 'is_active' => true,
    ]);
    $tdsReceivable = Account::create([
        'company_id' => $test->company->id, 'account_group_id' => $group->id,
        'name' => 'TDS Receivable', 'code' => 'TDSRCV-'.uniqid(), 'is_active' => true,
    ]);

    AccountSetting::create([
        'company_id' => $test->company->id,
        'customer_account_id' => $ar->id,
        'sales_account_id' => $sales->id,
        'cash_sales_account_id' => $sales->id,
        'bank_sales_account_id' => $sales->id,
        'tds_receivable_account_id' => $tdsReceivable->id,
    ]);

    return ['cash' => $cash, 'tds_receivable' => $tdsReceivable];
}

beforeEach(function () {
    tdsRcpWarmCache();
    TenantService::setCompanyId(null);
    TenantService::setBranchId(null);

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026-TDSRCP', 'year_code' => '26TDSRCP',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'TDS Rcp Co',
        'code' => 'TDSRCP-'.uniqid(),
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'TDS Rcp Tester',
        'email' => 'tdsrcp-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'TDS Payer',
        'code' => 'TDSPAY-'.uniqid(),
        'type' => PartyTypeEnum::CUSTOMER,
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Service Widget',
        'code' => 'SVCWGT-'.uniqid(),
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'SVSKU-'.uniqid(),
        'sales_price' => 10000,
        'is_default' => true,
    ]);

    $this->tdsTax = Tax::create([
        'company_id' => $this->company->id,
        'name' => 'TDS Service 1.5%',
        'rate' => 1.5,
        'type' => TaxTypeEnum::TDS->value,
        'tds_category' => TdsCategoryEnum::SERVICE_VAT_BILL->value,
    ]);

    TenantService::setCompanyId($this->company->id);
    Sanctum::actingAs($this->user, ['*'], 'admin');
});

it('approves a receipt with TDS deduction and posts correct GL journal', function () {
    ['cash' => $cash, 'tds_receivable' => $tdsReceivable] = tdsRcpSetup($this);

    // Create an approved invoice for NPR 10,000
    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'invoice_no' => 'INV-TDS-'.uniqid(),
        'invoice_date' => '2026-06-13',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
    ]);
    $invoice->invoiceItems()->create([
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
        'rate' => 10000,
        'tax_amount' => 0,
        'discount_amount' => 0,
    ]);

    // Receipt where customer pays NPR 9,850 cash + NPR 150 TDS deduction
    $receiptPayload = [
        'party_id' => $this->party->id,
        'receipt_date' => '2026-06-13',
        'payment_method' => 'cash',
        'account_id' => $cash->id,
        'status' => StatusEnum::APPROVED->value,
        'allocations' => [[
            'invoice_id' => $invoice->id,
            'amount' => 10000,
            'tds_id' => $this->tdsTax->id,
            'tds_deducted' => 150,
        ]],
    ];

    $response = $this->postJson('/api/admin/receipt', $receiptPayload);

    $response->assertCreated();

    $receiptId = $response->json('data.id');
    $receipt = \App\Models\Receipt::find($receiptId);

    // Verify allocation saved TDS fields
    $allocation = $receipt->allocations()->first();
    expect((float) $allocation->tds_deducted)->toBe(150.0);
    expect((float) $allocation->net_amount_received)->toBe(9850.0);

    // Verify GL journal has 3 lines: CR AR, DR Cash (net), DR TDS Receivable
    $journal = $receipt->journal()->withoutGlobalScopes()->first();
    expect($journal)->not->toBeNull();

    $items = $journal->journalItems()->get();
    expect($items)->toHaveCount(3);

    $arLine = $items->first(fn ($i) => (float) $i->cr_amount > 0);
    expect((float) $arLine->cr_amount)->toBe(10000.0);

    $cashLine = $items->first(fn ($i) => $i->account_id === $cash->id);
    expect((float) $cashLine->dr_amount)->toBe(9850.0);

    $tdsLine = $items->first(fn ($i) => $i->account_id === $tdsReceivable->id);
    expect((float) $tdsLine->dr_amount)->toBe(150.0);
});

it('records a TdsDeduction record linked to the receipt allocation', function () {
    ['cash' => $cash] = tdsRcpSetup($this);

    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'invoice_no' => 'INV-TDSDED-'.uniqid(),
        'invoice_date' => '2026-06-13',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
    ]);
    $invoice->invoiceItems()->create([
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
        'rate' => 10000,
        'tax_amount' => 0,
        'discount_amount' => 0,
    ]);

    $this->postJson('/api/admin/receipt', [
        'party_id' => $this->party->id,
        'receipt_date' => '2026-06-13',
        'payment_method' => 'cash',
        'account_id' => $cash->id,
        'status' => StatusEnum::APPROVED->value,
        'allocations' => [[
            'invoice_id' => $invoice->id,
            'amount' => 10000,
            'tds_id' => $this->tdsTax->id,
            'tds_deducted' => 150,
        ]],
    ])->assertCreated();

    expect(TdsDeduction::withoutGlobalScopes()->where('party_id', $this->party->id)->count())->toBe(1);

    $deduction = TdsDeduction::withoutGlobalScopes()->where('party_id', $this->party->id)->first();
    expect((float) $deduction->tds_amount)->toBe(150.0);
    expect((float) $deduction->base_amount)->toBe(10000.0);
    expect($deduction->receipt_allocation_id)->not->toBeNull();
});

it('rejects TDS deducted exceeding the allocation amount', function () {
    ['cash' => $cash] = tdsRcpSetup($this);

    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'invoice_no' => 'INV-TDSOVR-'.uniqid(),
        'invoice_date' => '2026-06-13',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
    ]);
    $invoice->invoiceItems()->create([
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
        'rate' => 10000,
        'tax_amount' => 0,
        'discount_amount' => 0,
    ]);

    $response = $this->postJson('/api/admin/receipt', [
        'party_id' => $this->party->id,
        'receipt_date' => '2026-06-13',
        'payment_method' => 'cash',
        'account_id' => $cash->id,
        'allocations' => [[
            'invoice_id' => $invoice->id,
            'amount' => 5000,
            'tds_id' => $this->tdsTax->id,
            'tds_deducted' => 6000, // exceeds amount
        ]],
    ]);

    $response->assertUnprocessable();
});

it('posts a standard 2-line journal when no TDS is deducted', function () {
    ['cash' => $cash] = tdsRcpSetup($this);

    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'invoice_no' => 'INV-NOTDS-'.uniqid(),
        'invoice_date' => '2026-06-13',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
    ]);
    $invoice->invoiceItems()->create([
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
        'rate' => 5000,
        'tax_amount' => 0,
        'discount_amount' => 0,
    ]);

    $response = $this->postJson('/api/admin/receipt', [
        'party_id' => $this->party->id,
        'receipt_date' => '2026-06-13',
        'payment_method' => 'cash',
        'account_id' => $cash->id,
        'status' => StatusEnum::APPROVED->value,
        'allocations' => [[
            'invoice_id' => $invoice->id,
            'amount' => 5000,
        ]],
    ]);

    $response->assertCreated();

    $receipt = \App\Models\Receipt::find($response->json('data.id'));
    $journal = $receipt->journal()->withoutGlobalScopes()->first();
    $items = $journal->journalItems()->get();

    expect($items)->toHaveCount(2);
});
