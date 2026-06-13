<?php

use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\CreditNote;
use App\Models\FiscalYear;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Enums\StatusEnum;
use App\Enums\PartyTypeEnum;
use App\Enums\UserTypeEnum;
use App\Enums\JournalTypeEnum;
use App\Models\AccountSetting;
use App\Models\InvoiceItem;
use App\Models\ProductVariant;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\TenantService;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function p2WarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

function p2AccountSetting(object $test): Account
{
    $account = Account::create([
        'company_id' => $test->company->id,
        'account_group_id' => null,
        'name' => 'P2 Cash',
        'code' => 'P2CASH-'.uniqid(),
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
    p2WarmCache();
    TenantService::setCompanyId(null);
    TenantService::setBranchId(null);

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'P2 Test Co',
        'code' => 'P2TC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'P2 Tester',
        'email' => 'p2-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Test Customer',
        'code' => 'CUST-'.uniqid(),
        'type' => PartyTypeEnum::CUSTOMER,
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'P2 Widget',
        'code' => 'P2W-'.uniqid(),
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'P2 Main',
        'code' => 'P2WH-'.uniqid(),
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'P2SKU-'.uniqid(),
        'sales_price' => 100,
        'is_default' => true,
    ]);

    TenantService::setCompanyId($this->company->id);
    Sanctum::actingAs($this->user, ['*'], 'admin');
});

// ─── P1-02: Approved quotation cannot be deleted ──────────────────────────────

it('P1-02: approved quotation cannot be deleted', function () {
    $quotation = Quotation::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'quotation_no' => 'QT-'.uniqid(),
        'quotation_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
    ]);

    $response = $this->deleteJson("/api/admin/quotation/{$quotation->id}");

    $response->assertStatus(422);
    expect($response->json('message'))->toContain('Approved quotations cannot be deleted');
    expect(Quotation::withTrashed()->find($quotation->id)?->deleted_at)->toBeNull();
});

it('P1-02: draft quotation can be deleted', function () {
    $quotation = Quotation::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'quotation_no' => 'QT-'.uniqid(),
        'quotation_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $response = $this->deleteJson("/api/admin/quotation/{$quotation->id}");

    $response->assertOk();
    expect(Quotation::withTrashed()->find($quotation->id)?->deleted_at)->not->toBeNull();
});

// ─── P1-02: Approved sales order cannot be deleted ───────────────────────────

it('P1-02: approved sales order cannot be deleted', function () {
    $order = SalesOrder::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'order_no' => 'SO-'.uniqid(),
        'order_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
    ]);

    $response = $this->deleteJson("/api/admin/sales-order/{$order->id}");

    $response->assertStatus(422);
    expect($response->json('message'))->toContain('Approved sales orders cannot be deleted');
    expect(SalesOrder::withTrashed()->find($order->id)?->deleted_at)->toBeNull();
});

it('P1-02: draft sales order can be deleted', function () {
    $order = SalesOrder::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'order_no' => 'SO-'.uniqid(),
        'order_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $response = $this->deleteJson("/api/admin/sales-order/{$order->id}");

    $response->assertOk();
    expect(SalesOrder::withTrashed()->find($order->id)?->deleted_at)->not->toBeNull();
});

// ─── P2-01: CreditNote GL correctly deducts order discount ───────────────────

it('P2-01: credit note GL deducts order-level discount from salesBase', function () {
    p2AccountSetting($this);

    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'invoice_no' => 'INV-P2-'.uniqid(),
        'invoice_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED,
    ]);

    $creditNote = CreditNote::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'invoice_id' => $invoice->id,
        'credit_note_no' => 'CN-P2-'.uniqid(),
        'credit_note_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::DRAFT,
    ]);

    $creditNote->creditNoteItems()->create([
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 2,
        'rate' => 100,
        'tax_amount' => 0,
        'discount_amount' => 0,
    ]);

    // Order-level 20 discount → salesBase should be 200 - 20 = 180
    $creditNote->saveDiscount('fixed', 20.0, 20.0);

    app(\App\Services\Accounting\CreditNoteGlPostingService::class)->postFromCreditNote($creditNote);

    $journal = Journal::withoutGlobalScopes()
        ->where('reference_type', $creditNote->getMorphClass())
        ->where('reference_id', $creditNote->id)
        ->where('type', JournalTypeEnum::CREDIT_NOTE)
        ->with('journalItems')
        ->first();

    expect($journal)->not->toBeNull('CreditNote GL journal must be posted');

    $crTotal = round((float) $journal->journalItems->sum('cr_amount'), 2);
    $drTotal = round((float) $journal->journalItems->sum('dr_amount'), 2);

    expect($drTotal)->toBe($crTotal, 'Credit note journal must balance');
    expect($crTotal)->toBe(180.0, 'AR credit must equal 200 - 20 order discount = 180');
});

// ─── P2-06: Credit note return quantity cannot exceed invoiced quantity ───────

it('P2-06: credit note item quantity cannot exceed the original invoice item quantity', function () {
    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'invoice_no' => 'INV-P2Q-'.uniqid(),
        'invoice_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
    ]);

    $invoiceItem = InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 3,
        'rate' => 100,
        'discount_amount' => 0,
        'tax_amount' => 0,
    ]);

    $payload = [
        'credit_note_date' => now()->toDateString(),
        'party_id' => $this->party->id,
        'invoice_id' => $invoice->id,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'invoice_item_id' => $invoiceItem->id,
            'quantity' => 5,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
        ]],
    ];

    $response = $this->postJson('/api/admin/credit-note', $payload);

    $response->assertStatus(422);
    expect($response->json('errors.items.0.quantity.0') ?? $response->json('message'))
        ->toContain('3');
});

it('P2-06: credit note item quantity equal to invoiced quantity is valid', function () {
    p2AccountSetting($this);

    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'invoice_no' => 'INV-P2QV-'.uniqid(),
        'invoice_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
    ]);

    $invoiceItem = InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 3,
        'rate' => 100,
        'discount_amount' => 0,
        'tax_amount' => 0,
    ]);

    $payload = [
        'credit_note_date' => now()->toDateString(),
        'party_id' => $this->party->id,
        'invoice_id' => $invoice->id,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'invoice_item_id' => $invoiceItem->id,
            'quantity' => 3,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
        ]],
    ];

    $response = $this->postJson('/api/admin/credit-note', $payload);

    $response->assertSuccessful();
});
