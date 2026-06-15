<?php

use App\Models\Bill;
use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\Product;
use App\Models\BillItem;
use App\Enums\StatusEnum;
use App\Models\Warehouse;
use App\Models\CreditNote;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Enums\ChangeTypeEnum;
use App\Enums\JournalTypeEnum;
use App\Enums\TaxLineTypeEnum;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use App\Services\Accounting\JournalVoidService;
use App\Services\Inventory\InventoryCostCalculator;
use App\Services\Accounting\CreditNoteGlPostingService;
use App\Services\Inventory\InventoryLayerReceiptService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function p3WarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

function p3SeedStock(object $test, int $qty): void
{
    p3WarmCache();

    $bill = Bill::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'bill_no' => 'BILL-P3-'.uniqid(),
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

function p3AccountSetting(object $test): AccountSetting
{
    $account = Account::create([
        'company_id' => $test->company->id,
        'account_group_id' => null,
        'name' => 'P3 Cash',
        'code' => 'P3CASH-'.uniqid(),
    ]);

    return AccountSetting::create([
        'company_id' => $test->company->id,
        'cash_sales_account_id' => $account->id,
        'bank_sales_account_id' => $account->id,
        'customer_account_id' => $account->id,
        'sales_account_id' => $account->id,
    ]);
}

beforeEach(function () {
    p3WarmCache();
    TenantService::setCompanyId(null);
    TenantService::setBranchId(null);

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'P3 Test Co',
        'code' => 'P3TC-'.uniqid(),
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'P3 Tester',
        'email' => 'p3-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Main', 'code' => 'P3W-'.uniqid(),
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'P3 Widget', 'code' => 'P3PROD-'.uniqid(),
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'P3SKU-'.uniqid(),
        'sales_price' => 100,
        'is_default' => true,
    ]);

    $this->customer = Party::create([
        'company_id' => $this->company->id,
        'name' => 'P3 Customer',
        'code' => 'P3CUST-'.uniqid(),
        'type' => PartyTypeEnum::CUSTOMER,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

// ─── Item 18: TDS accounts in AccountSetting ─────────────────────────────────

it('Item 18: account_settings has tds_payable_account_id and tds_receivable_account_id columns', function () {
    expect(Schema::hasColumn('account_settings', 'tds_payable_account_id'))->toBeTrue();
    expect(Schema::hasColumn('account_settings', 'tds_receivable_account_id'))->toBeTrue();
});

it('Item 18: AccountSetting can store TDS account IDs', function () {
    $account = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'TDS Payable',
        'code' => 'TDSP-'.uniqid(),
    ]);

    $setting = AccountSetting::create([
        'company_id' => $this->company->id,
        'tds_payable_account_id' => $account->id,
        'tds_receivable_account_id' => $account->id,
    ]);

    expect($setting->fresh()->tds_payable_account_id)->toBe($account->id);
    expect($setting->fresh()->tds_receivable_account_id)->toBe($account->id);
});

// ─── Item 21: CreditNoteGlPostingService filters by tax_line_type ────────────

it('Item 21: credit note GL does not post VAT for exempt lines with accidental tax_amount', function () {
    p3AccountSetting($this);

    $creditNote = CreditNote::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->customer->id,
        'credit_note_no' => 'CN-P3-'.uniqid(),
        'credit_note_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED,
    ]);

    // Exempt item — has tax_amount but tax_line_type is 'exempt' (data entry error)
    $creditNote->creditNoteItems()->create([
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 1,
        'rate' => 100,
        'tax_amount' => 5,
        'discount_amount' => 0,
        'tax_line_type' => TaxLineTypeEnum::EXEMPT->value,
    ]);

    app(CreditNoteGlPostingService::class)->postFromCreditNote($creditNote);

    $journal = Journal::withoutGlobalScopes()
        ->where('reference_type', $creditNote->getMorphClass())
        ->where('reference_id', $creditNote->id)
        ->where('type', JournalTypeEnum::CREDIT_NOTE->value)
        ->first();

    expect($journal)->not->toBeNull();

    $drTotal = round($journal->journalItems()->withoutGlobalScopes()->sum('dr_amount'), 2);
    $crTotal = round($journal->journalItems()->withoutGlobalScopes()->sum('cr_amount'), 2);

    expect($drTotal)->toBe($crTotal, 'Journal must be balanced');
    // sales base = 100 (exempt VAT not included in grand total)
    expect($crTotal)->toBe(100.0);
});

it('Item 21: credit note GL correctly separates taxable and exempt lines', function () {
    $account = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'P3 Cash B',
        'code' => 'P3CASHB-'.uniqid(),
    ]);

    $vatAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'P3 VAT',
        'code' => 'P3VAT-'.uniqid(),
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'cash_sales_account_id' => $account->id,
        'bank_sales_account_id' => $account->id,
        'customer_account_id' => $account->id,
        'sales_account_id' => $account->id,
        'vat_account_id' => $vatAccount->id,
    ]);

    $creditNote = CreditNote::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->customer->id,
        'credit_note_no' => 'CN-P3B-'.uniqid(),
        'credit_note_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED,
    ]);

    // Taxable: 100 + 13 VAT
    $creditNote->creditNoteItems()->create([
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 1,
        'rate' => 100,
        'tax_amount' => 13,
        'discount_amount' => 0,
        'tax_line_type' => TaxLineTypeEnum::TAXABLE->value,
    ]);

    // Exempt: 50 base, no VAT
    $creditNote->creditNoteItems()->create([
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 1,
        'rate' => 50,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'tax_line_type' => TaxLineTypeEnum::EXEMPT->value,
    ]);

    app(CreditNoteGlPostingService::class)->postFromCreditNote($creditNote);

    $journal = Journal::withoutGlobalScopes()
        ->where('reference_type', $creditNote->getMorphClass())
        ->where('reference_id', $creditNote->id)
        ->first();

    expect($journal)->not->toBeNull();

    $drTotal = round($journal->journalItems()->withoutGlobalScopes()->sum('dr_amount'), 2);
    $crTotal = round($journal->journalItems()->withoutGlobalScopes()->sum('cr_amount'), 2);

    expect($drTotal)->toBe($crTotal);
    // grand total = 100 (taxable) + 50 (exempt) + 13 (VAT on taxable only) = 163
    expect($crTotal)->toBe(163.0);
});

// ─── Item 22: JournalVoidService contra-entry journals ───────────────────────

it('Item 22: voiding an invoice creates a VOID contra-entry journal', function () {
    p3SeedStock($this, 5);
    p3AccountSetting($this);

    $createRes = $this->postJson('/api/admin/invoice', [
        'invoice_date' => now()->toDateString(),
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
            'tax_line_type' => TaxLineTypeEnum::TAXABLE->value,
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
        ]],
    ]);

    $createRes->assertCreated();
    $invoice = Invoice::orderByDesc('id')->first();
    $this->postJson("/api/admin/invoice/{$invoice->id}/approve")->assertOk();

    $this->postJson("/api/admin/invoice/{$invoice->id}/void")->assertOk();

    // Original journal should be soft-deleted
    $originalJournal = Journal::withoutGlobalScopes()
        ->where('reference_type', $invoice->getMorphClass())
        ->where('reference_id', $invoice->id)
        ->where('type', JournalTypeEnum::INVOICE->value)
        ->whereNotNull('deleted_at')
        ->first();

    expect($originalJournal)->not->toBeNull('Original INVOICE journal should be soft-deleted');

    // VOID contra-entry journal should be visible
    $voidJournal = Journal::withoutGlobalScopes()
        ->where('reference_type', $invoice->getMorphClass())
        ->where('reference_id', $invoice->id)
        ->where('type', JournalTypeEnum::VOID->value)
        ->whereNull('deleted_at')
        ->first();

    expect($voidJournal)->not->toBeNull('A VOID contra-entry journal must exist');
    expect($voidJournal->voucher_no)->toContain('VOID-');
});

it('Item 22: VOID journal DR/CR are exactly swapped from the original', function () {
    p3SeedStock($this, 5);
    p3AccountSetting($this);

    $createRes = $this->postJson('/api/admin/invoice', [
        'invoice_date' => now()->toDateString(),
        'party_id' => $this->customer->id,
        'status' => StatusEnum::DRAFT->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 1,
            'rate' => 200,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'tax_line_type' => TaxLineTypeEnum::TAXABLE->value,
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
        ]],
    ]);

    $createRes->assertCreated();
    $invoice = Invoice::orderByDesc('id')->first();
    $this->postJson("/api/admin/invoice/{$invoice->id}/approve")->assertOk();

    $originalJournal = Journal::withoutGlobalScopes()
        ->where('reference_type', $invoice->getMorphClass())
        ->where('reference_id', $invoice->id)
        ->where('type', JournalTypeEnum::INVOICE->value)
        ->first();

    $originalDr = $originalJournal->journalItems()->withoutGlobalScopes()->withTrashed()->sum('dr_amount');
    $originalCr = $originalJournal->journalItems()->withoutGlobalScopes()->withTrashed()->sum('cr_amount');

    $this->postJson("/api/admin/invoice/{$invoice->id}/void")->assertOk();

    $voidJournal = Journal::withoutGlobalScopes()
        ->where('reference_type', $invoice->getMorphClass())
        ->where('reference_id', $invoice->id)
        ->where('type', JournalTypeEnum::VOID->value)
        ->first();

    $voidDr = $voidJournal->journalItems()->withoutGlobalScopes()->sum('dr_amount');
    $voidCr = $voidJournal->journalItems()->withoutGlobalScopes()->sum('cr_amount');

    expect(round($voidDr, 2))->toBe(round($originalCr, 2));
    expect(round($voidCr, 2))->toBe(round($originalDr, 2));
});

it('Item 22: JournalVoidService is idempotent when document has no journal', function () {
    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->customer->id,
        'invoice_no' => 'INV-NOGLINV-'.uniqid(),
        'invoice_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $threw = false;

    try {
        app(JournalVoidService::class)->reverseForReference($invoice);
    } catch (\Throwable) {
        $threw = true;
    }

    expect($threw)->toBeFalse('reverseForReference should not throw when no journal exists');

    expect(Journal::withoutGlobalScopes()
        ->where('reference_type', $invoice->getMorphClass())
        ->where('reference_id', $invoice->id)
        ->count())->toBe(0);
});
