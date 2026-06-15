<?php

use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Product;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Models\PosSession;
use App\Enums\UserTypeEnum;
use App\Models\BankAccount;
use App\Models\InvoiceItem;
use App\Enums\PartyTypeEnum;
use App\Models\AccountGroup;
use Laravel\Sanctum\Sanctum;
use App\Models\ProductVariant;
use App\Services\TenantService;
use App\Models\BankStatementLine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function isolationWarmCache(): void
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
    isolationWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2081-82', 'year_code' => '8182',
        'start_date' => '2024-07-17', 'end_date' => '2025-07-16', 'is_current' => true,
    ]);

    // Company A — the authenticated tenant
    $this->companyA = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Company A', 'code' => 'ISOL-A',
        'inventory_costing_method' => 'fifo',
    ]);

    $this->userA = User::create([
        'company_id' => $this->companyA->id,
        'name' => 'User A', 'email' => 'usera@test.com',
        'password' => bcrypt('secret'), 'user_type' => UserTypeEnum::ADMIN,
    ]);

    $groupA = AccountGroup::create(['company_id' => $this->companyA->id, 'name' => 'Assets A', 'code' => 'ASSETS-A']);
    $this->accountA = Account::create([
        'company_id' => $this->companyA->id, 'account_group_id' => $groupA->id,
        'name' => 'Cash A', 'code' => 'CASH-A', 'is_active' => true,
    ]);

    $this->partyA = Party::withoutGlobalScopes()->create([
        'company_id' => $this->companyA->id,
        'type' => PartyTypeEnum::CUSTOMER,
        'name' => 'Party A', 'code' => 'PA001', 'is_active' => true,
    ]);

    // Company B — the other tenant whose data must not be visible
    $this->companyB = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Company B', 'code' => 'ISOL-B',
        'inventory_costing_method' => 'fifo',
    ]);

    $groupB = AccountGroup::create(['company_id' => $this->companyB->id, 'name' => 'Assets B', 'code' => 'ASSETS-B']);
    $this->accountB = Account::create([
        'company_id' => $this->companyB->id, 'account_group_id' => $groupB->id,
        'name' => 'Cash B', 'code' => 'CASH-B', 'is_active' => true,
    ]);

    $this->partyB = Party::withoutGlobalScopes()->create([
        'company_id' => $this->companyB->id,
        'type' => PartyTypeEnum::CUSTOMER,
        'name' => 'Party B', 'code' => 'PB001', 'is_active' => true,
    ]);

    Sanctum::actingAs($this->userA, ['*'], 'admin');
    TenantService::setCompanyId($this->companyA->id);
});

// ── H3: dueInvoices must not leak Company B invoices ─────────────────────────

it('dueInvoices returns only the authenticated company invoices — H3', function () {
    Invoice::withoutGlobalScopes()->create([
        'company_id' => $this->companyB->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->partyB->id,
        'invoice_no' => 'INV-B-001',
        'invoice_date' => '2024-08-01',
        'create_user_id' => $this->userA->id,
        'status' => StatusEnum::APPROVED,
    ]);

    $response = $this->getJson('/api/admin/invoice/due?party_id='.$this->partyB->id);

    $response->assertOk();
    // Company A user querying with Company B's party_id must get no results
    expect($response->json('data'))->toBeEmpty();
});

// ── H4: dueExpenses must not leak Company B expenses ────────────────────────

it('dueExpenses returns only the authenticated company expenses — H4', function () {
    Expense::withoutGlobalScopes()->create([
        'company_id' => $this->companyB->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->partyB->id,
        'expense_no' => 'EXP-B-001',
        'date' => '2024-08-01',
        'create_user_id' => $this->userA->id,
        'status' => StatusEnum::APPROVED,
    ]);

    $response = $this->getJson('/api/admin/expense/due?party_id='.$this->partyB->id);

    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
});

// ── H5: sales report dashboard summary must not aggregate Company B data ─────

it('sales report dashboard summary excludes Company B invoices — H5', function () {
    Invoice::withoutGlobalScopes()->create([
        'company_id' => $this->companyA->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->partyA->id,
        'invoice_no' => 'INV-A-001',
        'invoice_date' => '2024-08-01',
        'create_user_id' => $this->userA->id,
        'status' => StatusEnum::APPROVED,
    ]);
    Invoice::withoutGlobalScopes()->create([
        'company_id' => $this->companyB->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->partyB->id,
        'invoice_no' => 'INV-B-002',
        'invoice_date' => '2024-08-01',
        'create_user_id' => $this->userA->id,
        'status' => StatusEnum::APPROVED,
    ]);

    $response = $this->getJson('/api/admin/sales-report/dashboard');

    $response->assertOk();
    // Must count 1 (Company A only), not 2
    expect($response->json('data.summary.total_invoices'))->toBe(1);
});

// ── M1: salesByItems must not aggregate Company B line items ─────────────────

it('salesByItems aggregates only Company A invoice items — M1', function () {
    $productA = Product::withoutGlobalScopes()->create([
        'company_id' => $this->companyA->id, 'name' => 'Widget A', 'code' => 'WA-'.uniqid(),
    ]);
    $variantA = ProductVariant::withoutGlobalScopes()->create([
        'company_id' => $this->companyA->id, 'product_id' => $productA->id,
        'sku' => 'SKU-A-'.uniqid(), 'sales_price' => 50, 'is_default' => true,
    ]);

    $productB = Product::withoutGlobalScopes()->create([
        'company_id' => $this->companyB->id, 'name' => 'Widget B', 'code' => 'WB-'.uniqid(),
    ]);
    $variantB = ProductVariant::withoutGlobalScopes()->create([
        'company_id' => $this->companyB->id, 'product_id' => $productB->id,
        'sku' => 'SKU-B-'.uniqid(), 'sales_price' => 500, 'is_default' => true,
    ]);

    $invoiceA = Invoice::withoutGlobalScopes()->create([
        'company_id' => $this->companyA->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->partyA->id,
        'invoice_no' => 'INV-A-002',
        'invoice_date' => '2024-08-01',
        'create_user_id' => $this->userA->id,
        'status' => StatusEnum::APPROVED,
    ]);
    $invoiceB = Invoice::withoutGlobalScopes()->create([
        'company_id' => $this->companyB->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->partyB->id,
        'invoice_no' => 'INV-B-003',
        'invoice_date' => '2024-08-01',
        'create_user_id' => $this->userA->id,
        'status' => StatusEnum::APPROVED,
    ]);

    InvoiceItem::withoutGlobalScopes()->create([
        'invoice_id' => $invoiceA->id, 'product_variant_id' => $variantA->id,
        'quantity' => 3, 'rate' => 50, 'discount_amount' => 0, 'tax_amount' => 0,
    ]);
    InvoiceItem::withoutGlobalScopes()->create([
        'invoice_id' => $invoiceB->id, 'product_variant_id' => $variantB->id,
        'quantity' => 10, 'rate' => 500, 'discount_amount' => 0, 'tax_amount' => 0,
    ]);

    $response = $this->getJson('/api/admin/sales-report/sales-by-item?from_date=2024-07-17&to_date=2025-07-16');

    $response->assertOk();
    // Company A: 3 × 50 = 150; Company B: 10 × 500 = 5000 — total must be 150 only
    expect((float) $response->json('data.summary.amount'))->toBe(150.0);
});

// ── H1: matchLine must reject BankStatementLine from another company ──────────

it('matchLine returns 403 for a statement line belonging to another company — H1', function () {
    $bankAccountB = BankAccount::withoutGlobalScopes()->create([
        'company_id' => $this->companyB->id,
        'account_id' => $this->accountB->id,
        'bank_name' => 'Bank B', 'account_number' => 'B-001',
        'currency' => 'NPR', 'is_active' => true,
    ]);

    $lineB = BankStatementLine::create([
        'bank_account_id' => $bankAccountB->id,
        'transaction_date' => '2024-08-01',
        'description' => 'Payment', 'debit' => 0, 'credit' => 100, 'balance' => 100,
        'status' => 'unmatched',
    ]);

    $response = $this->postJson("/api/admin/bank-reconciliation/statement-lines/{$lineB->id}/match", [
        'journal_item_id' => 1,
    ]);

    $response->assertForbidden();
});

// ── H1: unmatchLine must reject BankStatementLine from another company ────────

it('unmatchLine returns 403 for a statement line belonging to another company — H1', function () {
    $bankAccountB = BankAccount::withoutGlobalScopes()->create([
        'company_id' => $this->companyB->id,
        'account_id' => $this->accountB->id,
        'bank_name' => 'Bank B2', 'account_number' => 'B-002',
        'currency' => 'NPR', 'is_active' => true,
    ]);

    $lineB = BankStatementLine::create([
        'bank_account_id' => $bankAccountB->id,
        'transaction_date' => '2024-08-01',
        'description' => 'Payment', 'debit' => 0, 'credit' => 200, 'balance' => 200,
        'status' => 'unmatched',
    ]);

    $response = $this->postJson("/api/admin/bank-reconciliation/statement-lines/{$lineB->id}/unmatch");

    $response->assertForbidden();
});

// ── M3: PosSession global scope filters by company_id ────────────────────────

it('PosSession::query() excludes sessions belonging to another company — M3', function () {
    PosSession::withoutGlobalScopes()->create([
        'company_id' => $this->companyB->id,
        'branch_id' => null,
        'user_id' => $this->userA->id,
        'opening_cash' => 500,
        'status' => 'open',
        'opened_at' => now(),
    ]);

    TenantService::setCompanyId($this->companyA->id);
    $count = PosSession::query()->count();

    expect($count)->toBe(0);
});
