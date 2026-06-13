<?php

use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\Product;
use App\Enums\StatusEnum;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\JournalItem;
use App\Enums\PartyTypeEnum;
use App\Models\AccountGroup;
use Laravel\Sanctum\Sanctum;
use App\Enums\ChargeTypeEnum;
use App\Models\InvoiceCharge;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function chargeWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

function makeChargeAccount(int $companyId, string $suffix = ''): Account
{
    $group = AccountGroup::create([
        'company_id' => $companyId,
        'name' => 'Income '.$suffix,
        'code' => 'INC-'.$suffix,
    ]);

    return Account::create([
        'company_id' => $companyId,
        'account_group_id' => $group->id,
        'name' => 'Freight Income '.$suffix,
        'code' => 'FRT-'.$suffix,
        'is_active' => true,
    ]);
}

function chargeInvoicePayload(object $test, array $charges = [], string $status = 'draft'): array
{
    return [
        'party_id' => $test->party->id,
        'invoice_date' => now()->toDateString(),
        'status' => $status,
        'items' => [
            [
                'product_variant_id' => $test->variant->id,
                'warehouse_id' => $test->warehouse->id,
                'quantity' => 2,
                'rate' => 500,
                'tax_id' => null,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'tax_line_type' => 'taxable',
            ],
        ],
        'charges' => $charges,
    ];
}

beforeEach(function () {
    chargeWarmCache();
    TenantService::setCompanyId(null);
    TenantService::setBranchId(null);

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026-CHG', 'year_code' => '26CHG',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Charge Test Co',
        'code' => 'CHGCO',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Charge Tester',
        'email' => 'chg-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Charge Customer',
        'code' => 'CHGCUST-'.uniqid(),
        'type' => PartyTypeEnum::CUSTOMER,
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Charge Widget',
        'code' => 'CHGWGT-'.uniqid(),
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Charge WH',
        'code' => 'CHGWH-'.uniqid(),
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'CHGSKU-'.uniqid(),
        'sales_price' => 500,
        'is_default' => true,
    ]);

    $group = AccountGroup::create([
        'company_id' => $this->company->id, 'name' => 'GL Accounts', 'code' => 'GLAC-'.uniqid(),
    ]);
    $this->arAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => $group->id,
        'name' => 'AR', 'code' => 'AR-'.uniqid(), 'is_active' => true,
    ]);
    $this->salesAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => $group->id,
        'name' => 'Sales', 'code' => 'SAL-'.uniqid(), 'is_active' => true,
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'customer_account_id' => $this->arAccount->id,
        'sales_account_id' => $this->salesAccount->id,
        'cash_sales_account_id' => $this->salesAccount->id,
        'bank_sales_account_id' => $this->salesAccount->id,
    ]);

    $this->chargeAccount = makeChargeAccount($this->company->id, uniqid());

    TenantService::setCompanyId($this->company->id);
    Sanctum::actingAs($this->user, ['*'], 'admin');
});

// ─── Validation ───────────────────────────────────────────────────────────────

it('rejects a charge with an invalid charge_type', function () {
    $payload = chargeInvoicePayload($this, [
        [
            'name' => 'Freight',
            'charge_type' => 'invalid_type',
            'account_id' => $this->chargeAccount->id,
            'amount' => 100,
        ],
    ]);

    $this->postJson('/api/admin/invoice', $payload)
        ->assertUnprocessable();
});

it('rejects a charge with a non-existent account_id', function () {
    $payload = chargeInvoicePayload($this, [
        [
            'name' => 'Freight',
            'charge_type' => ChargeTypeEnum::Freight->value,
            'account_id' => 99999,
            'amount' => 100,
        ],
    ]);

    $this->postJson('/api/admin/invoice', $payload)
        ->assertUnprocessable();
});

it('rejects a charge with a negative amount', function () {
    $payload = chargeInvoicePayload($this, [
        [
            'name' => 'Freight',
            'charge_type' => ChargeTypeEnum::Freight->value,
            'account_id' => $this->chargeAccount->id,
            'amount' => -50,
        ],
    ]);

    $this->postJson('/api/admin/invoice', $payload)
        ->assertUnprocessable();
});

// ─── Happy path ───────────────────────────────────────────────────────────────

it('creates an invoice with charges and returns charges in the response', function () {
    $payload = chargeInvoicePayload($this, [
        [
            'name' => 'Freight',
            'charge_type' => ChargeTypeEnum::Freight->value,
            'account_id' => $this->chargeAccount->id,
            'amount' => 200,
            'tax_id' => null,
            'tax_amount' => 0,
        ],
        [
            'name' => 'Handling',
            'charge_type' => ChargeTypeEnum::Handling->value,
            'account_id' => $this->chargeAccount->id,
            'amount' => 50,
            'tax_id' => null,
            'tax_amount' => 0,
        ],
    ]);

    $response = $this->postJson('/api/admin/invoice', $payload);

    $response->assertCreated();
    $data = $response->json('data');
    expect($data['charges'])->toHaveCount(2);
    expect((float) $data['charges_total'])->toBe(250.0);
    expect((float) $data['grand_total'])->toBe(1250.0);

    $invoice = Invoice::find($data['id']);
    expect($invoice->charges()->count())->toBe(2);
});

it('includes charges in grand_total calculation', function () {
    $payload = chargeInvoicePayload($this, [
        [
            'name' => 'Insurance',
            'charge_type' => ChargeTypeEnum::Insurance->value,
            'account_id' => $this->chargeAccount->id,
            'amount' => 300,
            'tax_amount' => 0,
        ],
    ]);

    $response = $this->postJson('/api/admin/invoice', $payload);

    $response->assertCreated();
    $data = $response->json('data');
    expect((float) $data['grand_total'])->toBe(1300.0);
    expect((float) $data['charges_total'])->toBe(300.0);
});

it('creates an invoice with no charges when charges is omitted', function () {
    $payload = chargeInvoicePayload($this);

    $response = $this->postJson('/api/admin/invoice', $payload);

    $response->assertCreated();
    $data = $response->json('data');
    expect($data['charges'])->toBeEmpty();
    expect((float) $data['charges_total'])->toBe(0.0);
    expect((float) $data['grand_total'])->toBe(1000.0);
});

it('replaces charges on update', function () {
    $payload = chargeInvoicePayload($this, [
        [
            'name' => 'Freight',
            'charge_type' => ChargeTypeEnum::Freight->value,
            'account_id' => $this->chargeAccount->id,
            'amount' => 100,
            'tax_amount' => 0,
        ],
    ]);

    $createResponse = $this->postJson('/api/admin/invoice', $payload);
    $createResponse->assertCreated();
    $invoiceId = $createResponse->json('data.id');

    $updatePayload = chargeInvoicePayload($this, [
        [
            'name' => 'Packaging',
            'charge_type' => ChargeTypeEnum::Packaging->value,
            'account_id' => $this->chargeAccount->id,
            'amount' => 75,
            'tax_amount' => 0,
        ],
        [
            'name' => 'Loading',
            'charge_type' => ChargeTypeEnum::Loading->value,
            'account_id' => $this->chargeAccount->id,
            'amount' => 25,
            'tax_amount' => 0,
        ],
    ]);

    $updateResponse = $this->putJson("/api/admin/invoice/{$invoiceId}", $updatePayload);
    $updateResponse->assertOk();

    $data = $updateResponse->json('data');
    expect($data['charges'])->toHaveCount(2);
    expect((float) $data['charges_total'])->toBe(100.0);

    $invoice = Invoice::find($invoiceId);
    expect($invoice->charges()->count())->toBe(2);
    expect($invoice->charges()->where('name', 'Freight')->exists())->toBeFalse();
});

it('removes all charges when update sends empty charges array', function () {
    $payload = chargeInvoicePayload($this, [
        [
            'name' => 'Freight',
            'charge_type' => ChargeTypeEnum::Freight->value,
            'account_id' => $this->chargeAccount->id,
            'amount' => 100,
            'tax_amount' => 0,
        ],
    ]);

    $createResponse = $this->postJson('/api/admin/invoice', $payload);
    $createResponse->assertCreated();
    $invoiceId = $createResponse->json('data.id');

    $updatePayload = chargeInvoicePayload($this, []);
    $this->putJson("/api/admin/invoice/{$invoiceId}", $updatePayload)->assertOk();

    expect(InvoiceCharge::where('invoice_id', $invoiceId)->count())->toBe(0);
});

// ─── GL posting with charges ──────────────────────────────────────────────────

it('posts a balanced GL journal including per-charge CR lines when invoice is approved', function () {
    // Use a service product so inventory deduction is skipped
    $serviceProduct = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Consulting Service',
        'code' => 'SVC-'.uniqid(),
        'product_type' => 'service',
    ]);
    $serviceVariant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $serviceProduct->id,
        'sku' => 'SVSKU-'.uniqid(),
        'sales_price' => 500,
        'is_default' => true,
    ]);

    $payload = [
        'party_id' => $this->party->id,
        'invoice_date' => now()->toDateString(),
        'status' => 'approved',
        'items' => [[
            'product_variant_id' => $serviceVariant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 2,
            'rate' => 500,
            'tax_id' => null,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'tax_line_type' => 'taxable',
        ]],
        'charges' => [[
            'name' => 'Freight',
            'charge_type' => ChargeTypeEnum::Freight->value,
            'account_id' => $this->chargeAccount->id,
            'amount' => 200,
            'tax_amount' => 0,
        ]],
    ];

    $response = $this->postJson('/api/admin/invoice', $payload);
    $response->assertCreated();

    $invoiceId = $response->json('data.id');
    $invoice = Invoice::find($invoiceId);

    $journal = Journal::withoutGlobalScopes()
        ->where('reference_id', $invoiceId)
        ->where('reference_type', $invoice->getMorphClass())
        ->first();

    expect($journal)->not->toBeNull();

    $items = JournalItem::where('journal_id', $journal->id)->get();

    // DR AR = 1000 (lines) + 200 (charge) = 1200
    $drAr = $items->where('account_id', $this->arAccount->id)->sum('dr_amount');
    expect((float) $drAr)->toBe(1200.0);

    // CR Sales = 1000 (lines only, no charges)
    $crSales = $items->where('account_id', $this->salesAccount->id)->sum('cr_amount');
    expect((float) $crSales)->toBe(1000.0);

    // CR Charge Account = 200
    $crCharge = $items->where('account_id', $this->chargeAccount->id)->sum('cr_amount');
    expect((float) $crCharge)->toBe(200.0);

    // Journal must balance
    $totalDr = round((float) $items->sum('dr_amount'), 2);
    $totalCr = round((float) $items->sum('cr_amount'), 2);
    expect($totalDr)->toBe($totalCr);
});

// ─── Enum ─────────────────────────────────────────────────────────────────────

it('ChargeTypeEnum has all expected cases with correct values', function () {
    expect(ChargeTypeEnum::Freight->value)->toBe('freight');
    expect(ChargeTypeEnum::Handling->value)->toBe('handling');
    expect(ChargeTypeEnum::Packaging->value)->toBe('packaging');
    expect(ChargeTypeEnum::Insurance->value)->toBe('insurance');
    expect(ChargeTypeEnum::Loading->value)->toBe('loading');
    expect(ChargeTypeEnum::Other->value)->toBe('other');
});

// ─── Model relationship ───────────────────────────────────────────────────────

it('InvoiceCharge casts charge_type to ChargeTypeEnum and belongs to Invoice', function () {
    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'invoice_no' => 'INV-CHG-'.uniqid(),
        'invoice_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    InvoiceCharge::create([
        'invoice_id' => $invoice->id,
        'name' => 'Freight',
        'charge_type' => ChargeTypeEnum::Freight->value,
        'account_id' => $this->chargeAccount->id,
        'amount' => 150,
        'tax_amount' => 0,
    ]);

    $invoice->load('charges');
    expect($invoice->charges)->toHaveCount(1);
    expect((float) $invoice->charges->first()->amount)->toBe(150.0);
    expect($invoice->charges->first()->charge_type)->toBe(ChargeTypeEnum::Freight);
});
