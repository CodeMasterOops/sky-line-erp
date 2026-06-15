<?php

use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\Product;
use App\Enums\StatusEnum;
use App\Models\CreditNote;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\InvoiceItem;
use App\Models\JournalItem;
use App\Enums\PartyTypeEnum;
use App\Models\AccountGroup;
use Laravel\Sanctum\Sanctum;
use App\Enums\JournalTypeEnum;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Services\TenantService;
use App\Enums\AccountGroupTypeEnum;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use App\Services\Accounting\ClosingEntryService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function accountP3WarmCache(): void
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
    accountP3WarmCache();

    $this->fy = FiscalYear::create([
        'year_name' => 'FY-P3', 'year_code' => 'P3',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);
    $this->company = Company::create([
        'fiscal_year_id' => $this->fy->id,
        'company_name' => 'P3 Co', 'code' => 'COP3',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);
    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'P3 User',
        'email' => 'p3-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);
    $this->account = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'GL-P3', 'code' => 'GLP3', 'is_active' => true,
    ]);
    $this->account2 = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'Cash-P3', 'code' => 'CASHP3', 'is_active' => true,
    ]);
    $this->party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Customer P3', 'code' => 'CUST-P3',
        'type' => PartyTypeEnum::CUSTOMER,
        'pan' => '111222333',
    ]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Widget P3', 'code' => 'WID-P3',
    ]);
    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'sku' => 'SKU-P3', 'sales_price' => 100, 'is_default' => true,
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'customer_account_id' => $this->account->id,
        'sales_account_id' => $this->account->id,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

// ─── P3.17: JournalItem uses Auditable trait ─────────────────────────────────

it('JournalItem model uses the Auditable trait', function () {
    expect(in_array(\App\Traits\Auditable::class, class_uses_recursive(JournalItem::class)))->toBeTrue();
});

// ─── P3.21: journals (company_id, voucher_no) unique index ───────────────────

it('journals table rejects duplicate voucher_no within the same company', function () {
    $jv = Journal::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fy->id,
        'type' => JournalTypeEnum::JOURNAL_VOUCHER,
        'voucher_no' => 'JV-UNIQ-001',
        'date' => '2026-06-01',
        'status' => StatusEnum::DRAFT,
        'create_user_id' => $this->user->id,
    ]);

    expect($jv->exists)->toBeTrue();

    // Second journal with the same voucher_no in the same company should throw.
    expect(fn () => Journal::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fy->id,
        'type' => JournalTypeEnum::JOURNAL_VOUCHER,
        'voucher_no' => 'JV-UNIQ-001',
        'date' => '2026-06-02',
        'status' => StatusEnum::DRAFT,
        'create_user_id' => $this->user->id,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('journals table allows the same voucher_no across different companies', function () {
    $fy2 = FiscalYear::create([
        'year_name' => 'FY-P3B', 'year_code' => 'P3B',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);
    $company2 = Company::create([
        'fiscal_year_id' => $fy2->id,
        'company_name' => 'P3 Co B', 'code' => 'COP3B',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    Journal::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fy->id,
        'type' => JournalTypeEnum::JOURNAL_VOUCHER,
        'voucher_no' => 'JV-CROSS-001',
        'date' => '2026-06-01',
        'status' => StatusEnum::DRAFT,
        'create_user_id' => $this->user->id,
    ]);

    // Same voucher_no in a different company is allowed.
    $jv2 = Journal::create([
        'company_id' => $company2->id,
        'fiscal_year_id' => $fy2->id,
        'type' => JournalTypeEnum::JOURNAL_VOUCHER,
        'voucher_no' => 'JV-CROSS-001',
        'date' => '2026-06-01',
        'status' => StatusEnum::DRAFT,
        'create_user_id' => $this->user->id,
    ]);

    expect($jv2->exists)->toBeTrue();
});

// ─── P3.24: AR aging subtracts approved credit notes ─────────────────────────

it('arAging subtracts approved credit note amounts from invoice outstanding', function () {
    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fy->id,
        'party_id' => $this->party->id,
        'invoice_no' => 'INV-P3-CN-001',
        'invoice_date' => '2026-06-01',
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 1, 'rate' => 1000,
        'discount_amount' => 0, 'tax_amount' => 0,
        'tax_line_type' => 'zero_rated',
    ]);

    // Credit note for 300 against this invoice.
    $cn = CreditNote::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fy->id,
        'party_id' => $this->party->id,
        'invoice_id' => $invoice->id,
        'credit_note_no' => 'CN-P3-001',
        'credit_note_date' => '2026-06-05',
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    $cn->creditNoteItems()->create([
        'product_variant_id' => $this->variant->id,
        'quantity' => 1, 'rate' => 300,
        'discount_amount' => 0, 'tax_amount' => 0,
        'tax_line_type' => 'zero_rated',
    ]);

    $response = $this->getJson('/api/admin/account-report/ar-aging');

    $response->assertOk();
    $rows = collect($response->json('data.rows'));
    $row = $rows->firstWhere('invoice_no', 'INV-P3-CN-001');

    // 1000 (invoice) - 300 (credit note) = 700 outstanding.
    expect($row)->not->toBeNull()
        ->and((float) $row['outstanding'])->toBe(700.0);
});

it('arAging excludes invoices fully covered by credit notes', function () {
    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fy->id,
        'party_id' => $this->party->id,
        'invoice_no' => 'INV-P3-FULL-CN',
        'invoice_date' => '2026-06-01',
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 1, 'rate' => 500,
        'discount_amount' => 0, 'tax_amount' => 0,
        'tax_line_type' => 'zero_rated',
    ]);

    $cn = CreditNote::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fy->id,
        'party_id' => $this->party->id,
        'invoice_id' => $invoice->id,
        'credit_note_no' => 'CN-P3-FULL',
        'credit_note_date' => '2026-06-05',
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    $cn->creditNoteItems()->create([
        'product_variant_id' => $this->variant->id,
        'quantity' => 1, 'rate' => 500,
        'discount_amount' => 0, 'tax_amount' => 0,
        'tax_line_type' => 'zero_rated',
    ]);

    $response = $this->getJson('/api/admin/account-report/ar-aging');

    $response->assertOk();
    $rows = collect($response->json('data.rows'));

    // Fully covered by credit note — must not appear in aging.
    expect($rows->firstWhere('invoice_no', 'INV-P3-FULL-CN'))->toBeNull();
});

it('arAging does not subtract voided credit notes', function () {
    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fy->id,
        'party_id' => $this->party->id,
        'invoice_no' => 'INV-P3-VOID-CN',
        'invoice_date' => '2026-06-01',
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 1, 'rate' => 800,
        'discount_amount' => 0, 'tax_amount' => 0,
        'tax_line_type' => 'zero_rated',
    ]);

    $cn = CreditNote::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fy->id,
        'party_id' => $this->party->id,
        'invoice_id' => $invoice->id,
        'credit_note_no' => 'CN-P3-VOID',
        'credit_note_date' => '2026-06-05',
        'status' => StatusEnum::APPROVED,
        'voided_at' => now(),
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    $cn->creditNoteItems()->create([
        'product_variant_id' => $this->variant->id,
        'quantity' => 1, 'rate' => 800,
        'discount_amount' => 0, 'tax_amount' => 0,
        'tax_line_type' => 'zero_rated',
    ]);

    $response = $this->getJson('/api/admin/account-report/ar-aging');

    $response->assertOk();
    $rows = collect($response->json('data.rows'));
    $row = $rows->firstWhere('invoice_no', 'INV-P3-VOID-CN');

    // Voided credit note should not reduce the outstanding — full 800 due.
    expect($row)->not->toBeNull()
        ->and((float) $row['outstanding'])->toBe(800.0);
});

// ─── P3.23: ClosingEntryService uses account_type for nominal account detection ─

it('ClosingEntryService uses account_type Income/Expense to identify nominal accounts', function () {
    $incomeGroup = AccountGroup::create([
        'company_id' => $this->company->id,
        'name' => 'Revenue Streams',
        'code' => 'REV',
        'account_type' => AccountGroupTypeEnum::Income,
    ]);
    $expenseGroup = AccountGroup::create([
        'company_id' => $this->company->id,
        'name' => 'Costs of Operations',
        'code' => 'COST',
        'account_type' => AccountGroupTypeEnum::Expense,
    ]);
    $retainedGroup = AccountGroup::create([
        'company_id' => $this->company->id,
        'name' => 'Equity',
        'code' => 'EQ',
        'account_type' => AccountGroupTypeEnum::Equity,
    ]);

    $incomeAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => $incomeGroup->id,
        'name' => 'Sales Rev', 'code' => 'SREV', 'is_active' => true,
    ]);
    $expenseAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => $expenseGroup->id,
        'name' => 'Operating Cost', 'code' => 'OPCOST', 'is_active' => true,
    ]);
    $retainedEarnings = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => $retainedGroup->id,
        'name' => 'Retained Earnings', 'code' => 'RE', 'is_active' => true,
    ]);

    // Balanced journal: DR expense 400 / CR income 400.
    $journal = Journal::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fy->id,
        'type' => JournalTypeEnum::JOURNAL_VOUCHER,
        'voucher_no' => 'JV-CLOSE-TYPE-001',
        'date' => '2026-06-15',
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    JournalItem::create(['journal_id' => $journal->id, 'account_id' => $expenseAccount->id, 'dr_amount' => 400, 'cr_amount' => 0]);
    JournalItem::create(['journal_id' => $journal->id, 'account_id' => $incomeAccount->id, 'dr_amount' => 0, 'cr_amount' => 400]);

    $service = app(ClosingEntryService::class);
    $result = $service->post($this->company->id, $this->fy, $retainedEarnings->id, $this->user->id);

    expect($result['posted'])->toBeTrue()
        ->and($result['accounts_closed'])->toBe(2);
});

it('ClosingEntryService falls back to name-based matching when account_type is null', function () {
    $incomeGroup = AccountGroup::create([
        'company_id' => $this->company->id,
        'name' => 'Income',  // name-based fallback
        'code' => 'INC-FB',
        'account_type' => null,
    ]);
    $expenseGroup = AccountGroup::create([
        'company_id' => $this->company->id,
        'name' => 'Expenses',  // name-based fallback
        'code' => 'EXP-FB',
        'account_type' => null,
    ]);

    $incomeAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => $incomeGroup->id,
        'name' => 'Sales-FB', 'code' => 'SFB', 'is_active' => true,
    ]);
    $expenseAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => $expenseGroup->id,
        'name' => 'Wages-FB', 'code' => 'WFB', 'is_active' => true,
    ]);
    $retainedEarnings = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'RE-FB', 'code' => 'REFB', 'is_active' => true,
    ]);

    $journal = Journal::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fy->id,
        'type' => JournalTypeEnum::JOURNAL_VOUCHER,
        'voucher_no' => 'JV-CLOSE-NAME-001',
        'date' => '2026-06-15',
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    JournalItem::create(['journal_id' => $journal->id, 'account_id' => $expenseAccount->id, 'dr_amount' => 600, 'cr_amount' => 0]);
    JournalItem::create(['journal_id' => $journal->id, 'account_id' => $incomeAccount->id, 'dr_amount' => 0, 'cr_amount' => 600]);

    $service = app(ClosingEntryService::class);
    $result = $service->post($this->company->id, $this->fy, $retainedEarnings->id, $this->user->id);

    expect($result['posted'])->toBeTrue()
        ->and($result['accounts_closed'])->toBe(2);
});
