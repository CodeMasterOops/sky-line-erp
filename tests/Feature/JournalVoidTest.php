<?php

use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\Product;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\InvoiceItem;
use App\Models\JournalItem;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Enums\JournalTypeEnum;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use App\Services\Accounting\JournalVoidService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function journalVoidWarmAllTablesCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $tables[$table] = Schema::getColumnListing($table);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

function makePostedInvoiceJournal(object $test, string $no): array
{
    $invoice = Invoice::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'invoice_no' => $no,
        'invoice_date' => now()->toDateString(),
        'party_id' => $test->customer->id,
        'create_user_id' => $test->user->id,
        'status' => StatusEnum::APPROVED,
        'approved_at' => now(),
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_variant_id' => $test->variant->id,
        'quantity' => 1,
        'rate' => 100,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'tax_line_type' => 'taxable',
    ]);

    $journal = Journal::withoutGlobalScopes()->create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'type' => JournalTypeEnum::INVOICE,
        'reference_type' => $invoice->getMorphClass(),
        'reference_id' => $invoice->id,
        'voucher_no' => 'SALE-JV-'.$invoice->id,
        'date' => now()->toDateString(),
        'create_user_id' => $test->user->id,
        'approve_user_id' => $test->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED,
    ]);

    JournalItem::create([
        'journal_id' => $journal->id,
        'account_id' => $test->arAccount->id,
        'dr_amount' => 100, 'cr_amount' => 0,
    ]);
    JournalItem::create([
        'journal_id' => $journal->id,
        'account_id' => $test->salesAccount->id,
        'dr_amount' => 0, 'cr_amount' => 100,
    ]);

    return [$invoice, $journal];
}

beforeEach(function () {
    journalVoidWarmAllTablesCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Void Co', 'code' => 'VOC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Voider',
        'email' => 'voider-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->customer = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Customer', 'code' => 'CUST-VOID',
        'type' => PartyTypeEnum::CUSTOMER,
    ]);

    $product = Product::create([
        'company_id' => $this->company->id, 'name' => 'Widget', 'code' => 'WIDGET-VOID',
    ]);
    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id, 'product_id' => $product->id,
        'sku' => 'SKU-VOID-1', 'sales_price' => 100, 'is_default' => true,
    ]);
    $this->arAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'AR', 'code' => 'AR-VOID',
    ]);
    $this->salesAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'Sales', 'code' => 'SALES-VOID',
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

it('soft-deletes the journal and its items when reversing a posted document', function () {
    [$invoice, $journal] = makePostedInvoiceJournal($this, 'INV-VOID-1');

    expect(Journal::withoutGlobalScopes()->whereNull('deleted_at')->count())->toBe(1);
    expect(JournalItem::withoutGlobalScopes()->whereNull('deleted_at')->count())->toBe(2);

    app(JournalVoidService::class)->reverseForReference($invoice);

    expect(Journal::withoutGlobalScopes()->whereNull('deleted_at')->count())->toBe(0);
    expect(JournalItem::withoutGlobalScopes()->whereNull('deleted_at')->count())->toBe(0);
    // Rows still exist on disk as an audit trail.
    expect(Journal::withoutGlobalScopes()->count())->toBe(1);
    expect(JournalItem::withoutGlobalScopes()->count())->toBe(2);
});

it('removes the voided journal from GL balance computations', function () {
    [$invoice] = makePostedInvoiceJournal($this, 'INV-VOID-2');

    // Same filter the trial balance / P&L use: deleted journals are excluded.
    $liveBalanceCount = DB::table('journal_items')
        ->join('journals', 'journals.id', '=', 'journal_items.journal_id')
        ->where('journals.company_id', $this->company->id)
        ->whereNull('journals.deleted_at')
        ->count();

    expect($liveBalanceCount)->toBe(2);

    app(JournalVoidService::class)->reverseForReference($invoice);

    $liveBalanceCount = DB::table('journal_items')
        ->join('journals', 'journals.id', '=', 'journal_items.journal_id')
        ->where('journals.company_id', $this->company->id)
        ->whereNull('journals.deleted_at')
        ->count();

    expect($liveBalanceCount)->toBe(0);
});

it('is idempotent: a second reversal is a safe no-op', function () {
    [$invoice] = makePostedInvoiceJournal($this, 'INV-VOID-3');

    $service = app(JournalVoidService::class);
    $service->reverseForReference($invoice);
    $service->reverseForReference($invoice); // must not throw, no extra rows

    expect(Journal::withoutGlobalScopes()->count())->toBe(1);
    expect(Journal::withoutGlobalScopes()->whereNull('deleted_at')->count())->toBe(0);
});

it('is a no-op for a document that never had a journal', function () {
    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'invoice_no' => 'INV-NO-JV',
        'invoice_date' => now()->toDateString(),
        'party_id' => $this->customer->id,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    app(JournalVoidService::class)->reverseForReference($invoice);

    expect(Journal::withoutGlobalScopes()->count())->toBe(0);
});
