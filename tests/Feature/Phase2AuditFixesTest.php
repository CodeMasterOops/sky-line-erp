<?php

use App\Models\Bill;
use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\BillItem;
use App\Enums\StatusEnum;
use App\Models\Quotation;
use App\Models\Warehouse;
use App\Models\CreditNote;
use App\Models\FiscalYear;
use App\Models\SalesOrder;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Enums\ChangeTypeEnum;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use App\Services\Inventory\InventoryCostCalculator;
use App\Services\Inventory\InventoryLayerReceiptService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function p2aWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
}

function p2aSeedStock(object $test, int $qty): void
{
    p2aWarmCache();

    $bill = Bill::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'bill_no' => 'BILL-P2A-'.uniqid(),
        'bill_date' => now()->toDateString(),
        'create_user_id' => $test->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $item = BillItem::create([
        'bill_id' => $bill->id,
        'product_variant_id' => $test->variant->id,
        'warehouse_id' => $test->warehouse->id,
        'quantity' => $qty,
        'rate' => 50,
        'discount_amount' => 0,
    ]);

    DB::transaction(function () use ($test, $bill, $item, $qty) {
        app(InventoryLayerReceiptService::class)->receive(
            $test->company,
            $bill,
            $test->variant->id,
            $test->warehouse->id,
            $qty,
            InventoryCostCalculator::unitCostFromBillItem($item),
            ChangeTypeEnum::PURCHASE,
            $test->user->id,
            null,
            $item->id,
        );
    });
}

function p2aAccountSetting(object $test): Account
{
    $account = Account::create([
        'company_id' => $test->company->id,
        'account_group_id' => null,
        'name' => 'P2A Cash',
        'code' => 'P2ACASH-'.uniqid(),
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

function p2aInvoicePayload(object $test, array $overrides = []): array
{
    return array_merge([
        'invoice_date' => now()->toDateString(),
        'party_id' => $test->customer->id,
        'status' => StatusEnum::DRAFT->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'items' => [[
            'product_variant_id' => $test->variant->id,
            'warehouse_id' => $test->warehouse->id,
            'quantity' => 1,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'tax_line_type' => 'taxable',
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
        ]],
    ], $overrides);
}

beforeEach(function () {
    p2aWarmCache();
    TenantService::setCompanyId(null);
    TenantService::setBranchId(null);

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'P2A Test Co',
        'code' => 'P2ATC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'P2A Tester',
        'email' => 'p2a-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Main', 'code' => 'P2AW',
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'P2A Widget', 'code' => 'P2AW-'.uniqid(),
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'P2ASKU-'.uniqid(),
        'sales_price' => 100,
        'is_default' => true,
    ]);

    $this->customer = Party::create([
        'company_id' => $this->company->id,
        'name' => 'P2A Customer',
        'code' => 'P2ACUST-'.uniqid(),
        'type' => PartyTypeEnum::CUSTOMER,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

// ─── Item 8: InvoiceRequest fiscal year date validation ───────────────────────

it('Item 8: invoice_date outside fiscal year is rejected', function () {
    $response = $this->postJson('/api/admin/invoice', p2aInvoicePayload($this, [
        'invoice_date' => '2025-01-01', // outside 2026 fiscal year
    ]));

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['invoice_date']);
});

it('Item 8: invoice_date within fiscal year is accepted', function () {
    $response = $this->postJson('/api/admin/invoice', p2aInvoicePayload($this, [
        'invoice_date' => '2026-06-15',
    ]));

    $response->assertCreated();
});

// ─── Item 9: approveQuotation and approveSalesOrder wrapped in DB::transaction

it('Item 9: approveQuotation sets status to approved', function () {
    $quotation = Quotation::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->customer->id,
        'quotation_no' => 'QT-TEST-'.uniqid(),
        'quotation_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $response = $this->postJson("/api/admin/quotation/{$quotation->id}/approve");

    $response->assertOk();
    expect($quotation->fresh()->status)->toBe(StatusEnum::APPROVED);
    expect($quotation->fresh()->approve_user_id)->toBe($this->user->id);
});

it('Item 9: approveSalesOrder sets status to approved', function () {
    $order = SalesOrder::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->customer->id,
        'order_no' => 'SO-TEST-'.uniqid(),
        'order_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $response = $this->postJson("/api/admin/sales-order/{$order->id}/approve");

    $response->assertOk();
    expect($order->fresh()->status)->toBe(StatusEnum::APPROVED);
    expect($order->fresh()->approve_user_id)->toBe($this->user->id);
});

// ─── Item 13: salesReport is paginated ───────────────────────────────────────

it('Item 13: salesReport response includes pagination metadata', function () {
    p2aSeedStock($this, 5);
    p2aAccountSetting($this);

    // Create and approve 3 invoices
    foreach (range(1, 3) as $i) {
        $createRes = $this->postJson('/api/admin/invoice', p2aInvoicePayload($this));
        $createRes->assertCreated();
        $invoice = Invoice::orderByDesc('id')->first();
        $this->postJson("/api/admin/invoice/{$invoice->id}/approve")->assertOk();
    }

    $response = $this->getJson('/api/admin/sales-report/report?limit=2');

    $response->assertOk();
    expect($response->json('data.pagination'))->toMatchArray([
        'current_page' => 1,
        'per_page' => 2,
        'total' => 3,
        'last_page' => 2,
    ]);
    expect(count($response->json('data.rows')))->toBe(2);
});

it('Item 13: salesReport summary covers all matching invoices, not just current page', function () {
    p2aSeedStock($this, 10);
    p2aAccountSetting($this);

    foreach (range(1, 3) as $i) {
        $createRes = $this->postJson('/api/admin/invoice', p2aInvoicePayload($this));
        $createRes->assertCreated();
        $invoice = Invoice::orderByDesc('id')->first();
        $this->postJson("/api/admin/invoice/{$invoice->id}/approve")->assertOk();
    }

    // Paginate to only 1 row per page
    $response = $this->getJson('/api/admin/sales-report/report?limit=1');

    $response->assertOk();
    // Summary should cover all 3 invoices (3 × 100 = 300 total)
    expect((float) $response->json('data.summary.total_amount'))->toBe(300.0);
    expect($response->json('data.summary.total_invoices'))->toBe(3);
    // Rows only returns 1
    expect(count($response->json('data.rows')))->toBe(1);
});

// ─── Item 14: CreditNoteService SRP ──────────────────────────────────────────

it('Item 14: CreditNoteController delegates to CreditNoteService (store still works)', function () {
    $response = $this->postJson('/api/admin/credit-note', [
        'credit_note_date' => now()->toDateString(),
        'party_id' => $this->customer->id,
        'status' => StatusEnum::DRAFT->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 1,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
        ]],
    ]);

    $response->assertCreated();
    expect(CreditNote::count())->toBe(1);
});

// ─── Item 15: explicit company_id on quotations and sales orders ──────────────

it('Item 15: createQuotation persists company_id explicitly', function () {
    $response = $this->postJson('/api/admin/quotation', [
        'quotation_date' => now()->toDateString(),
        'party_id' => $this->customer->id,
        'status' => StatusEnum::DRAFT->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'quantity' => 1,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
        ]],
    ]);

    $response->assertCreated();
    $quotation = Quotation::first();
    expect($quotation->company_id)->toBe($this->company->id);
});

it('Item 15: createSalesOrder persists company_id explicitly', function () {
    $response = $this->postJson('/api/admin/sales-order', [
        'order_date' => now()->toDateString(),
        'party_id' => $this->customer->id,
        'status' => StatusEnum::DRAFT->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'quantity' => 1,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
        ]],
    ]);

    $response->assertCreated();
    $order = SalesOrder::first();
    expect($order->company_id)->toBe($this->company->id);
});
