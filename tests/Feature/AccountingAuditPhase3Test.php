<?php

use App\Models\Tax;
use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\Product;
use App\Models\TaxGroup;
use App\Enums\StatusEnum;
use App\Models\Warehouse;
use App\Enums\TaxTypeEnum;
use App\Models\CreditNote;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\BankAccount;
use App\Models\InvoiceItem;
use App\Models\JournalItem;
use Laravel\Sanctum\Sanctum;
use App\Enums\JournalTypeEnum;
use App\Models\AccountSetting;
use App\Models\CreditNoteItem;
use App\Models\ProductVariant;
use App\Models\TaxGroupMember;
use App\Services\TenantService;
use App\Models\BankStatementLine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function auditP3WarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
}

function auditP3Setup(string $suffix): array
{
    $fy = FiscalYear::create([
        'year_name' => "FY-{$suffix}", 'year_code' => $suffix,
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);
    $company = Company::create([
        'fiscal_year_id' => $fy->id,
        'company_name' => "Co-{$suffix}", 'code' => "CO{$suffix}",
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);
    $user = User::create([
        'company_id' => $company->id,
        'name' => "U-{$suffix}",
        'email' => "u-{$suffix}-".uniqid().'@x.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    return [$fy, $company, $user];
}

beforeEach(function () {
    auditP3WarmCache();
});

// ---------------------------------------------------------------------------
// WithinActiveFiscalYear rule — Receipt date validation
// ---------------------------------------------------------------------------

it('rejects a receipt dated outside the active fiscal year', function () {
    [$fy, $company, $user] = auditP3Setup('RFY1');

    $arAccount = Account::create([
        'company_id' => $company->id, 'account_group_id' => null,
        'name' => 'AR', 'code' => 'AR-RFY1',
    ]);
    $salesAccount = Account::create([
        'company_id' => $company->id, 'account_group_id' => null,
        'name' => 'Sales', 'code' => 'SALES-RFY1',
    ]);
    $bankAccount = Account::create([
        'company_id' => $company->id, 'account_group_id' => null,
        'name' => 'Bank', 'code' => 'BANK-RFY1',
    ]);
    AccountSetting::create([
        'company_id' => $company->id,
        'customer_account_id' => $arAccount->id,
        'sales_account_id' => $salesAccount->id,
    ]);

    $party = Party::create([
        'company_id' => $company->id,
        'name' => 'Cust-RFY1', 'code' => 'CRF1-'.uniqid(), 'type' => 'customer',
    ]);

    $invoice = Invoice::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $fy->id,
        'party_id' => $party->id,
        'invoice_no' => 'INV-RFY1',
        'invoice_date' => '2026-03-01',
        'status' => StatusEnum::APPROVED,
        'total_amount' => 1000,
        'paid_amount' => 0,
        'create_user_id' => $user->id,
        'approve_user_id' => $user->id,
        'approved_at' => now(),
    ]);

    Sanctum::actingAs($user, ['*'], 'admin');
    TenantService::setCompanyId($company->id);

    // Date outside 2026 fiscal year — must be rejected
    $this->postJson('/api/admin/receipt', [
        'receipt_date' => '2025-12-31',
        'party_id' => $party->id,
        'payment_method' => 'bank',
        'account_id' => $bankAccount->id,
        'allocations' => [
            ['invoice_id' => $invoice->id, 'amount' => 1000],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrorFor('receipt_date');
});

it('accepts a receipt dated within the active fiscal year', function () {
    [$fy, $company, $user] = auditP3Setup('RFY2');

    $arAccount = Account::create([
        'company_id' => $company->id, 'account_group_id' => null,
        'name' => 'AR', 'code' => 'AR-RFY2',
    ]);
    $salesAccount = Account::create([
        'company_id' => $company->id, 'account_group_id' => null,
        'name' => 'Sales', 'code' => 'SALES-RFY2',
    ]);
    $bankAccount = Account::create([
        'company_id' => $company->id, 'account_group_id' => null,
        'name' => 'Bank', 'code' => 'BANK-RFY2',
    ]);
    AccountSetting::create([
        'company_id' => $company->id,
        'customer_account_id' => $arAccount->id,
        'sales_account_id' => $salesAccount->id,
        'bank_sales_account_id' => $bankAccount->id,
        'cash_sales_account_id' => $bankAccount->id,
    ]);

    $party = Party::create([
        'company_id' => $company->id,
        'name' => 'Cust-RFY2', 'code' => 'CRF2-'.uniqid(), 'type' => 'customer',
    ]);

    $invoice = Invoice::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $fy->id,
        'party_id' => $party->id,
        'invoice_no' => 'INV-RFY2',
        'invoice_date' => '2026-03-01',
        'status' => StatusEnum::APPROVED,
        'total_amount' => 1000,
        'paid_amount' => 0,
        'create_user_id' => $user->id,
        'approve_user_id' => $user->id,
        'approved_at' => now(),
    ]);

    Sanctum::actingAs($user, ['*'], 'admin');
    TenantService::setCompanyId($company->id);

    // Date within 2026 fiscal year — must be accepted
    $this->postJson('/api/admin/receipt', [
        'receipt_date' => '2026-06-15',
        'party_id' => $party->id,
        'payment_method' => 'bank',
        'account_id' => $bankAccount->id,
        'allocations' => [
            ['invoice_id' => $invoice->id, 'amount' => 1000],
        ],
    ])->assertSuccessful();
});

// ---------------------------------------------------------------------------
// Credit note GL posting — per-tax-group accounts
// ---------------------------------------------------------------------------

it('posts per-tax-group GL lines when credit note items have a tax_group_id', function () {
    [$fy, $company, $user] = auditP3Setup('CNTG');

    $arAccount = Account::create(['company_id' => $company->id, 'account_group_id' => null, 'name' => 'AR', 'code' => 'AR-CNTG']);
    $salesAccount = Account::create(['company_id' => $company->id, 'account_group_id' => null, 'name' => 'Sales', 'code' => 'SALES-CNTG']);
    $vatAccount = Account::create(['company_id' => $company->id, 'account_group_id' => null, 'name' => 'VAT Output', 'code' => 'VATOUT-CNTG']);

    AccountSetting::create([
        'company_id' => $company->id,
        'customer_account_id' => $arAccount->id,
        'sales_account_id' => $salesAccount->id,
        'vat_account_id' => $vatAccount->id,
    ]);

    // Tax with a specific GL account
    $tax = Tax::create([
        'company_id' => $company->id,
        'name' => 'VAT 13%',
        'rate' => 13,
        'type' => TaxTypeEnum::VAT_STANDARD,
        'calculation_type' => 'percentage',
        'is_inclusive' => false,
        'is_compound' => false,
        'sequence' => 1,
        'gl_account_id' => $vatAccount->id,
    ]);
    $taxGroup = TaxGroup::create([
        'company_id' => $company->id,
        'name' => 'Standard VAT',
        'is_active' => true,
    ]);
    TaxGroupMember::create([
        'tax_group_id' => $taxGroup->id,
        'tax_id' => $tax->id,
        'sequence' => 1,
    ]);

    $party = Party::create([
        'company_id' => $company->id,
        'name' => 'Cust-CNTG', 'code' => 'CNTG-'.uniqid(), 'type' => 'customer',
    ]);
    $product = Product::create(['company_id' => $company->id, 'name' => 'P-CNTG', 'code' => 'PCNTG']);
    $variant = ProductVariant::create([
        'company_id' => $company->id, 'product_id' => $product->id,
        'sku' => 'SKU-CNTG', 'is_default' => true, 'sales_price' => 100,
    ]);
    $warehouse = Warehouse::create(['company_id' => $company->id, 'name' => 'WH-CNTG', 'code' => 'WHCNTG']);

    $invoice = Invoice::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $fy->id,
        'party_id' => $party->id,
        'invoice_no' => 'INV-CNTG',
        'invoice_date' => '2026-03-01',
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $user->id,
        'approve_user_id' => $user->id,
        'approved_at' => now(),
    ]);
    $invoiceItem = InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_variant_id' => $variant->id,
        'quantity' => 2,
        'rate' => 100,
        'discount_amount' => 0,
        'tax_amount' => 26, // 13% of 200
    ]);

    $creditNote = CreditNote::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $fy->id,
        'party_id' => $party->id,
        'invoice_id' => $invoice->id,
        'credit_note_no' => 'CN-CNTG',
        'credit_note_date' => '2026-04-01',
        'status' => StatusEnum::DRAFT,
        'create_user_id' => $user->id,
    ]);
    CreditNoteItem::create([
        'credit_note_id' => $creditNote->id,
        'invoice_item_id' => $invoiceItem->id,
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 2,
        'rate' => 100,
        'discount_amount' => 0,
        'tax_amount' => 26,
        'tax_line_type' => 'taxable',
        'tax_group_id' => $taxGroup->id,
    ]);

    Sanctum::actingAs($user, ['*'], 'admin');
    TenantService::setCompanyId($company->id);

    $this->postJson("/api/admin/credit-note/{$creditNote->id}/approve")->assertOk();

    // GL journal must use the tax group's specific VAT account
    $journal = Journal::withoutGlobalScopes()
        ->where('company_id', $company->id)
        ->where('type', JournalTypeEnum::CREDIT_NOTE)
        ->first();

    expect($journal)->not->toBeNull();

    $vatLine = $journal->journalItems()
        ->where('account_id', $vatAccount->id)
        ->first();

    // VAT line must exist and DR 26 (reversing the output VAT)
    expect($vatLine)->not->toBeNull();
    expect((float) $vatLine->dr_amount)->toBe(26.0);
});

it('falls back to the generic VAT account when credit note items have no tax_group_id', function () {
    [$fy, $company, $user] = auditP3Setup('CNFB');

    $arAccount = Account::create(['company_id' => $company->id, 'account_group_id' => null, 'name' => 'AR', 'code' => 'AR-CNFB']);
    $salesAccount = Account::create(['company_id' => $company->id, 'account_group_id' => null, 'name' => 'Sales', 'code' => 'SALES-CNFB']);
    $vatAccount = Account::create(['company_id' => $company->id, 'account_group_id' => null, 'name' => 'VAT Output', 'code' => 'VATOUT-CNFB']);

    AccountSetting::create([
        'company_id' => $company->id,
        'customer_account_id' => $arAccount->id,
        'sales_account_id' => $salesAccount->id,
        'vat_account_id' => $vatAccount->id,
    ]);

    $party = Party::create([
        'company_id' => $company->id,
        'name' => 'Cust-CNFB', 'code' => 'CNFB-'.uniqid(), 'type' => 'customer',
    ]);
    $product = Product::create(['company_id' => $company->id, 'name' => 'P-CNFB', 'code' => 'PCNFB']);
    $variant = ProductVariant::create([
        'company_id' => $company->id, 'product_id' => $product->id,
        'sku' => 'SKU-CNFB', 'is_default' => true, 'sales_price' => 100,
    ]);
    $warehouse = Warehouse::create(['company_id' => $company->id, 'name' => 'WH-CNFB', 'code' => 'WHCNFB']);

    $invoice = Invoice::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $fy->id,
        'party_id' => $party->id,
        'invoice_no' => 'INV-CNFB',
        'invoice_date' => '2026-03-01',
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $user->id,
        'approve_user_id' => $user->id,
        'approved_at' => now(),
    ]);
    $invoiceItem = InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_variant_id' => $variant->id,
        'quantity' => 1,
        'rate' => 100,
        'discount_amount' => 0,
        'tax_amount' => 13,
    ]);

    $creditNote = CreditNote::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $fy->id,
        'party_id' => $party->id,
        'invoice_id' => $invoice->id,
        'credit_note_no' => 'CN-CNFB',
        'credit_note_date' => '2026-04-01',
        'status' => StatusEnum::DRAFT,
        'create_user_id' => $user->id,
    ]);
    // No tax_group_id — should fall back to generic VAT account
    CreditNoteItem::create([
        'credit_note_id' => $creditNote->id,
        'invoice_item_id' => $invoiceItem->id,
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 1,
        'rate' => 100,
        'discount_amount' => 0,
        'tax_amount' => 13,
        'tax_line_type' => 'taxable',
        'tax_group_id' => null,
    ]);

    Sanctum::actingAs($user, ['*'], 'admin');
    TenantService::setCompanyId($company->id);

    $this->postJson("/api/admin/credit-note/{$creditNote->id}/approve")->assertOk();

    $journal = Journal::withoutGlobalScopes()
        ->where('company_id', $company->id)
        ->where('type', JournalTypeEnum::CREDIT_NOTE)
        ->first();

    expect($journal)->not->toBeNull();

    $vatLine = $journal->journalItems()
        ->where('account_id', $vatAccount->id)
        ->first();

    expect($vatLine)->not->toBeNull();
    expect((float) $vatLine->dr_amount)->toBe(13.0);
});

// ---------------------------------------------------------------------------
// Bank reconciliation GL balance — approved journals only
// ---------------------------------------------------------------------------

it('excludes draft journal items from the GL balance in bank reconciliation', function () {
    [$fy, $company, $user] = auditP3Setup('BRGB');

    $glAccount = Account::create([
        'company_id' => $company->id, 'account_group_id' => null,
        'name' => 'Bank BRGB', 'code' => 'BRGB-GL',
    ]);
    $bankAccount = BankAccount::create([
        'company_id' => $company->id,
        'account_id' => $glAccount->id,
        'bank_name' => 'Test Bank BRGB',
        'account_number' => 'ACC-BRGB',
    ]);

    // Approved journal — should count in GL balance
    $approvedJournal = Journal::withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'fiscal_year_id' => $fy->id,
        'type' => JournalTypeEnum::JOURNAL_VOUCHER,
        'date' => '2026-03-01',
        'voucher_no' => 'JV-BRGB-APV',
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $user->id,
        'approve_user_id' => $user->id,
        'approved_at' => now(),
    ]);
    JournalItem::create([
        'journal_id' => $approvedJournal->id,
        'account_id' => $glAccount->id,
        'dr_amount' => 0,
        'cr_amount' => 5000,
    ]);

    // Draft journal — must NOT count in GL balance
    $draftJournal = Journal::withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'fiscal_year_id' => $fy->id,
        'type' => JournalTypeEnum::JOURNAL_VOUCHER,
        'date' => '2026-03-01',
        'voucher_no' => 'JV-BRGB-DRF',
        'status' => StatusEnum::DRAFT,
        'create_user_id' => $user->id,
    ]);
    JournalItem::create([
        'journal_id' => $draftJournal->id,
        'account_id' => $glAccount->id,
        'dr_amount' => 0,
        'cr_amount' => 99999, // large amount that would skew balance if included
    ]);

    Sanctum::actingAs($user, ['*'], 'admin');
    TenantService::setCompanyId($company->id);

    $response = $this->getJson("/api/admin/bank-reconciliation/bank-accounts/{$bankAccount->id}/statement-lines");

    $response->assertOk();
    // GL balance must be 5000 (approved only), not 104999 (if draft were included)
    expect((float) $response->json('summary.gl_balance'))->toBe(5000.0);
});

// ---------------------------------------------------------------------------
// Bank reconciliation auto-match
// ---------------------------------------------------------------------------

it('auto-matches bank statement lines with matching approved GL entries', function () {
    [$fy, $company, $user] = auditP3Setup('BRAM');

    $glAccount = Account::create([
        'company_id' => $company->id, 'account_group_id' => null,
        'name' => 'Bank BRAM', 'code' => 'BRAM-GL',
    ]);
    $bankAccount = BankAccount::create([
        'company_id' => $company->id,
        'account_id' => $glAccount->id,
        'bank_name' => 'Test Bank BRAM',
        'account_number' => 'ACC-BRAM',
    ]);

    // GL journal item
    $journal = Journal::withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'fiscal_year_id' => $fy->id,
        'type' => JournalTypeEnum::JOURNAL_VOUCHER,
        'date' => '2026-03-10',
        'voucher_no' => 'JV-BRAM',
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $user->id,
        'approve_user_id' => $user->id,
        'approved_at' => now(),
    ]);
    $journalItem = JournalItem::create([
        'journal_id' => $journal->id,
        'account_id' => $glAccount->id,
        'dr_amount' => 0,
        'cr_amount' => 2500,
    ]);

    // Bank statement line with matching credit and date
    BankStatementLine::create([
        'bank_account_id' => $bankAccount->id,
        'transaction_date' => '2026-03-10',
        'reference' => 'TXN-BRAM',
        'debit' => 0,
        'credit' => 2500,
        'status' => 'unmatched',
    ]);

    Sanctum::actingAs($user, ['*'], 'admin');
    TenantService::setCompanyId($company->id);

    $response = $this->postJson("/api/admin/bank-reconciliation/bank-accounts/{$bankAccount->id}/auto-match");

    $response->assertOk();
    expect($response->json('message'))->toContain('1');

    $line = BankStatementLine::where('bank_account_id', $bankAccount->id)->first();
    expect($line->status)->toBe('matched')
        ->and($line->journal_item_id)->toBe($journalItem->id);
});
