<?php

use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Journal;
use App\Models\Product;
use App\Enums\StatusEnum;
use App\Models\DebitNote;
use App\Models\Warehouse;
use App\Models\CreditNote;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\DebitNoteItem;
use App\Enums\JournalTypeEnum;
use App\Models\AccountSetting;
use App\Models\CreditNoteItem;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function returnGlWarmAllTablesCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

function returnGlAccount(object $test, string $code): Account
{
    return Account::create([
        'company_id' => $test->company->id,
        'account_group_id' => null,
        'name' => $code,
        'code' => $code,
    ]);
}

function makeApprovedCreditNote(object $test, string $no, float $rate, int $qty, float $tax = 0): CreditNote
{
    $note = CreditNote::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'party_id' => $test->customer->id,
        'credit_note_no' => $no,
        'credit_note_date' => now()->toDateString(),
        'create_user_id' => $test->user->id,
        'approve_user_id' => $test->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED,
    ]);

    CreditNoteItem::create([
        'credit_note_id' => $note->id,
        'product_variant_id' => $test->variant->id,
        'warehouse_id' => $test->warehouse->id,
        'quantity' => $qty,
        'rate' => $rate,
        'tax_amount' => $tax,
        'discount_amount' => 0,
    ]);

    return $note;
}

function makeApprovedDebitNote(object $test, string $no, float $rate, int $qty, float $tax = 0): DebitNote
{
    $note = DebitNote::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'party_id' => $test->supplier->id,
        'debit_note_no' => $no,
        'debit_note_date' => now()->toDateString(),
        'create_user_id' => $test->user->id,
        'approve_user_id' => $test->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED,
    ]);

    DebitNoteItem::create([
        'debit_note_id' => $note->id,
        'product_variant_id' => $test->variant->id,
        'warehouse_id' => $test->warehouse->id,
        'quantity' => $qty,
        'rate' => $rate,
        'tax_amount' => $tax,
        'discount_amount' => 0,
    ]);

    return $note;
}

beforeEach(function () {
    returnGlWarmAllTablesCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Return Co',
        'code' => 'RTN',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Returner',
        'email' => 'returner-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->customer = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Customer',
        'code' => 'CUST-RTN',
        'type' => PartyTypeEnum::CUSTOMER,
    ]);

    $this->supplier = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Supplier',
        'code' => 'SUP-RTN',
        'type' => PartyTypeEnum::SUPPLIER,
    ]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Widget',
        'code' => 'WIDGET-RTN',
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'sku' => 'SKU-RTN-1',
        'sales_price' => 100,
        'is_default' => true,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Main',
        'code' => 'W-RTN',
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

function configureSalesAndPurchaseAccounts(object $test): void
{
    AccountSetting::create([
        'company_id' => $test->company->id,
        'customer_account_id' => returnGlAccount($test, 'AR-RTN')->id,
        'sales_account_id' => returnGlAccount($test, 'SALES-RTN')->id,
        'supplier_account_id' => returnGlAccount($test, 'AP-RTN')->id,
        'purchase_account_id' => returnGlAccount($test, 'PUR-RTN')->id,
        'vat_account_id' => returnGlAccount($test, 'VAT-RTN')->id,
    ]);
}

it('posts a balanced sales-return journal when re-posting a credit note', function () {
    configureSalesAndPurchaseAccounts($this);
    $note = makeApprovedCreditNote($this, 'CN-1', 100, 2, 26); // base 200, vat 26, total 226

    expect($this->getJson('/api/admin/account-report/unposted-documents')
        ->json('data.summary.credit_note_count'))->toBe(1);

    $this->postJson("/api/admin/account-report/credit-note/{$note->id}/repost")
        ->assertSuccessful()
        ->assertJsonPath('message', 'Credit note posted to the ledger successfully.');

    $journal = Journal::withoutGlobalScopes()
        ->where('reference_type', (new CreditNote)->getMorphClass())
        ->where('reference_id', $note->id)
        ->where('type', JournalTypeEnum::CREDIT_NOTE->value)
        ->with('journalItems')
        ->firstOrFail();

    $dr = round($journal->journalItems->sum('dr_amount'), 2);
    $cr = round($journal->journalItems->sum('cr_amount'), 2);

    expect($dr)->toBe(226.0)
        ->and($cr)->toBe(226.0); // balanced: DR sales 200 + DR vat 26 = CR receivable 226

    expect($this->getJson('/api/admin/account-report/unposted-documents')
        ->json('data.summary.credit_note_count'))->toBe(0);
});

it('posts a balanced purchase-return journal when re-posting a debit note', function () {
    configureSalesAndPurchaseAccounts($this);
    $note = makeApprovedDebitNote($this, 'DN-1', 50, 2, 13); // base 100, vat 13, total 113

    expect($this->getJson('/api/admin/account-report/unposted-documents')
        ->json('data.summary.debit_note_count'))->toBe(1);

    $this->postJson("/api/admin/account-report/debit-note/{$note->id}/repost")
        ->assertSuccessful()
        ->assertJsonPath('message', 'Debit note posted to the ledger successfully.');

    $journal = Journal::withoutGlobalScopes()
        ->where('reference_type', (new DebitNote)->getMorphClass())
        ->where('reference_id', $note->id)
        ->where('type', JournalTypeEnum::DEBIT_NOTE->value)
        ->with('journalItems')
        ->firstOrFail();

    $dr = round($journal->journalItems->sum('dr_amount'), 2);
    $cr = round($journal->journalItems->sum('cr_amount'), 2);

    expect($dr)->toBe(113.0)
        ->and($cr)->toBe(113.0); // balanced: DR payable 113 = CR purchase 100 + CR vat 13

    expect($this->getJson('/api/admin/account-report/unposted-documents')
        ->json('data.summary.debit_note_count'))->toBe(0);
});

it('credit-note re-post is idempotent', function () {
    configureSalesAndPurchaseAccounts($this);
    $note = makeApprovedCreditNote($this, 'CN-2', 100, 1);

    $this->postJson("/api/admin/account-report/credit-note/{$note->id}/repost")->assertSuccessful();
    $this->postJson("/api/admin/account-report/credit-note/{$note->id}/repost")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Credit note is already posted to the ledger.');

    expect(Journal::withoutGlobalScopes()
        ->where('reference_id', $note->id)
        ->where('reference_type', (new CreditNote)->getMorphClass())
        ->count())->toBe(1);
});

it('refuses to re-post a credit note when sales accounts are not configured', function () {
    $note = makeApprovedCreditNote($this, 'CN-3', 100, 1);

    $this->postJson("/api/admin/account-report/credit-note/{$note->id}/repost")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['account_setting']);

    expect(Journal::withoutGlobalScopes()->count())->toBe(0);
});

it('refuses to re-post a debit note when purchase accounts are not configured', function () {
    $note = makeApprovedDebitNote($this, 'DN-3', 50, 1);

    $this->postJson("/api/admin/account-report/debit-note/{$note->id}/repost")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['account_setting']);

    expect(Journal::withoutGlobalScopes()->count())->toBe(0);
});
