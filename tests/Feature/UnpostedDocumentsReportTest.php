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
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Enums\JournalTypeEnum;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function unpostedWarmAllTablesCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $tables[$table] = Schema::getColumnListing($table);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

function makeApprovedInvoice(object $test, string $no, float $rate, int $qty): Invoice
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
        'quantity' => $qty,
        'rate' => $rate,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'tax_line_type' => 'taxable',
    ]);

    return $invoice;
}

beforeEach(function () {
    unpostedWarmAllTablesCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Unposted Co',
        'code' => 'UNP',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Reporter',
        'email' => 'reporter-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->customer = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Customer',
        'code' => 'CUST-UNP',
        'type' => PartyTypeEnum::CUSTOMER,
    ]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Widget',
        'code' => 'WIDGET-UNP',
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'sku' => 'SKU-UNP-1',
        'sales_price' => 100,
        'is_default' => true,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

it('lists approved invoices that have no journal and excludes posted ones', function () {
    $unposted = makeApprovedInvoice($this, 'INV-UNP-1', 100, 2); // 200
    $posted = makeApprovedInvoice($this, 'INV-UNP-2', 50, 1);    // excluded

    // Attach a journal to the posted invoice via the morph relation.
    $posted->journal()->create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'type' => JournalTypeEnum::INVOICE->value,
        'voucher_no' => 'SALE-JV-'.$posted->id,
        'date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED->value,
    ]);

    $response = $this->getJson('/api/admin/account-report/unposted-documents');

    $response->assertSuccessful()
        ->assertJsonPath('data.summary.sales_count', 1)
        ->assertJsonPath('data.summary.total_count', 1)
        ->assertJsonPath('data.sales_invoices.0.document_no', 'INV-UNP-1')
        ->assertJsonPath('data.sales_invoices.0.amount', 200);

    expect(Journal::withoutGlobalScopes()->count())->toBe(1);
    expect($unposted->fresh()->journal)->toBeNull();
});

it('excludes draft and voided invoices', function () {
    // draft (not approved)
    Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'invoice_no' => 'INV-DRAFT',
        'invoice_date' => now()->toDateString(),
        'party_id' => $this->customer->id,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    // approved but voided
    $voided = makeApprovedInvoice($this, 'INV-VOID', 100, 1);
    $voided->update(['voided_at' => now()]);

    $response = $this->getJson('/api/admin/account-report/unposted-documents');

    $response->assertSuccessful()
        ->assertJsonPath('data.summary.total_count', 0);
});

function configureSalesAccounts(object $test): void
{
    $account = Account::create([
        'company_id' => $test->company->id,
        'account_group_id' => null,
        'name' => 'Control',
        'code' => 'CTRL-UNP',
    ]);

    AccountSetting::create([
        'company_id' => $test->company->id,
        'customer_account_id' => $account->id,
        'sales_account_id' => $account->id,
    ]);
}

it('re-posts an unposted approved invoice and removes it from the report', function () {
    configureSalesAccounts($this);
    $invoice = makeApprovedInvoice($this, 'INV-REPOST', 100, 2); // 200

    expect($this->getJson('/api/admin/account-report/unposted-documents')
        ->json('data.summary.total_count'))->toBe(1);

    $this->postJson("/api/admin/account-report/invoice/{$invoice->id}/repost")
        ->assertSuccessful()
        ->assertJsonPath('message', 'Invoice posted to the ledger successfully.');

    expect(Journal::withoutGlobalScopes()
        ->where('reference_type', (new Invoice)->getMorphClass())
        ->where('reference_id', $invoice->id)
        ->count())->toBe(1);

    expect($this->getJson('/api/admin/account-report/unposted-documents')
        ->json('data.summary.total_count'))->toBe(0);
});

it('re-post is idempotent and rejects an already-posted invoice', function () {
    configureSalesAccounts($this);
    $invoice = makeApprovedInvoice($this, 'INV-REPOST-2', 100, 1);

    $this->postJson("/api/admin/account-report/invoice/{$invoice->id}/repost")
        ->assertSuccessful();

    // Second attempt must not create a duplicate journal.
    $this->postJson("/api/admin/account-report/invoice/{$invoice->id}/repost")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Invoice is already posted to the ledger.');

    expect(Journal::withoutGlobalScopes()
        ->where('reference_id', $invoice->id)
        ->where('reference_type', (new Invoice)->getMorphClass())
        ->count())->toBe(1);
});

it('refuses to re-post when GL control accounts are not configured', function () {
    $invoice = makeApprovedInvoice($this, 'INV-REPOST-3', 100, 1);

    $this->postJson("/api/admin/account-report/invoice/{$invoice->id}/repost")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['account_setting']);

    expect(Journal::withoutGlobalScopes()->count())->toBe(0);
});

it('refuses to re-post a voided invoice', function () {
    configureSalesAccounts($this);
    $invoice = makeApprovedInvoice($this, 'INV-REPOST-4', 100, 1);
    $invoice->update(['voided_at' => now()]);

    $this->postJson("/api/admin/account-report/invoice/{$invoice->id}/repost")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Only approved, non-voided invoices can be re-posted.');
});
