<?php

use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\Product;
use App\Models\Receipt;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\InvoiceItem;
use App\Models\JournalItem;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Enums\JournalTypeEnum;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Services\TenantService;
use App\Models\ReceiptAllocation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function reconcileWarmAllTablesCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

beforeEach(function () {
    reconcileWarmAllTablesCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Reconcile Co', 'code' => 'REC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Reconciler',
        'email' => 'rec-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->customer = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Customer', 'code' => 'CUST-REC',
        'type' => PartyTypeEnum::CUSTOMER,
    ]);

    $product = Product::create([
        'company_id' => $this->company->id, 'name' => 'Widget', 'code' => 'WIDGET-REC',
    ]);
    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id, 'product_id' => $product->id,
        'sku' => 'SKU-REC-1', 'sales_price' => 100, 'is_default' => true,
    ]);

    $this->arAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'AR', 'code' => 'AR-REC',
    ]);
    $this->salesAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'Sales', 'code' => 'SALES-REC',
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'customer_account_id' => $this->arAccount->id,
        'sales_account_id' => $this->salesAccount->id,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

function makeApprovedInvoiceWithJournal(object $test, string $no, float $total): Invoice
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
        'rate' => $total,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'tax_line_type' => 'taxable',
    ]);

    // Matching balanced journal: DR AR $total, CR Sales $total.
    $journal = Journal::withoutGlobalScopes()->create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'type' => JournalTypeEnum::INVOICE,
        'reference_type' => $invoice->getMorphClass(),
        'reference_id' => $invoice->id,
        'voucher_no' => 'SALE-JV-'.$invoice->id,
        'date' => now()->toDateString(),
        'create_user_id' => $test->user->id,
        'status' => StatusEnum::APPROVED,
    ]);
    JournalItem::create([
        'journal_id' => $journal->id, 'account_id' => $test->arAccount->id,
        'dr_amount' => $total, 'cr_amount' => 0,
    ]);
    JournalItem::create([
        'journal_id' => $journal->id, 'account_id' => $test->salesAccount->id,
        'dr_amount' => 0, 'cr_amount' => $total,
    ]);

    return $invoice;
}

it('reports AR as reconciled when sub-ledger and GL agree', function () {
    makeApprovedInvoiceWithJournal($this, 'INV-REC-1', 500);
    makeApprovedInvoiceWithJournal($this, 'INV-REC-2', 300);

    $response = $this->getJson('/api/admin/account-report/ar-ap-reconciliation');

    $response->assertSuccessful()
        ->assertJsonPath('data.ar.subledger_balance', 800)
        ->assertJsonPath('data.ar.gl_balance', 800)
        ->assertJsonPath('data.ar.delta', 0)
        ->assertJsonPath('data.ar.reconciled', true);
});

it('reports AR as out-of-balance when a journal is missing for an approved invoice', function () {
    makeApprovedInvoiceWithJournal($this, 'INV-REC-3', 500);

    // Approve a second invoice without posting a journal (legacy unposted doc).
    Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'invoice_no' => 'INV-REC-UNPOSTED',
        'invoice_date' => now()->toDateString(),
        'party_id' => $this->customer->id,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
        'approved_at' => now(),
    ])->invoiceItems()->create([
        'product_variant_id' => $this->variant->id,
        'quantity' => 1, 'rate' => 200, 'discount_amount' => 0, 'tax_amount' => 0, 'tax_line_type' => 'taxable',
    ]);

    $response = $this->getJson('/api/admin/account-report/ar-ap-reconciliation');

    // Sub-ledger sees 500 + 200 = 700; GL only has the first invoice's 500.
    $response->assertSuccessful()
        ->assertJsonPath('data.ar.subledger_balance', 700)
        ->assertJsonPath('data.ar.gl_balance', 500)
        ->assertJsonPath('data.ar.delta', 200)
        ->assertJsonPath('data.ar.reconciled', false);
});

it('reduces AR sub-ledger by receipt allocations', function () {
    $invoice = makeApprovedInvoiceWithJournal($this, 'INV-REC-4', 500);

    // Receipt for 200 of the 500.
    $receipt = Receipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->customer->id,
        'receipt_no' => 'RC-REC-1',
        'receipt_date' => now()->toDateString(),
        'payment_method' => 'cash',
        'account_id' => $this->arAccount->id,
        'amount' => 200,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED,
    ]);
    ReceiptAllocation::create([
        'receipt_id' => $receipt->id,
        'invoice_id' => $invoice->id,
        'amount' => 200,
    ]);

    $response = $this->getJson('/api/admin/account-report/ar-ap-reconciliation');

    // Sub-ledger drops to 300; GL still 500 (no receipt journal in this test) → delta -200.
    $response->assertSuccessful()
        ->assertJsonPath('data.ar.subledger_balance', 300)
        ->assertJsonPath('data.ar.gl_balance', 500)
        ->assertJsonPath('data.ar.delta', -200)
        ->assertJsonPath('data.ar.reconciled', false);
});

it('reports account_configured=false when no control account is set', function () {
    // Replace the existing AccountSetting with one missing customer_account_id.
    AccountSetting::query()->delete();
    AccountSetting::create([
        'company_id' => $this->company->id,
        'sales_account_id' => $this->salesAccount->id,
    ]);

    $response = $this->getJson('/api/admin/account-report/ar-ap-reconciliation');

    $response->assertSuccessful()
        ->assertJsonPath('data.ar.account_configured', false)
        ->assertJsonPath('data.ar.gl_balance', 0);
});
