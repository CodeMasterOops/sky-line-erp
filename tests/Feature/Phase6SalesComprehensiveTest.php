<?php

use App\Models\User;
use App\Models\Party;
use App\Models\Branch;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\Product;
use App\Enums\StatusEnum;
use App\Models\Warehouse;
use App\Models\CreditNote;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\InvoiceItem;
use App\Models\JournalItem;
use Laravel\Sanctum\Sanctum;
use App\Enums\JournalTypeEnum;
use App\Models\AccountSetting;
use App\Models\CreditNoteItem;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function p6SalesWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
}

beforeEach(function () {
    p6SalesWarmCache();
    TenantService::setBranchId(null);

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2081-82',
        'year_code' => '8182',
        'start_date' => '2024-07-17',
        'end_date' => '2025-07-16',
        'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Phase6 Test Co',
        'code' => 'P6TC-'.uniqid(),
        'inventory_costing_method' => 'fifo',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Phase6 Tester',
        'email' => 'p6-'.uniqid().'@test.com',
        'password' => bcrypt('secret'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Test Customer',
        'code' => 'P6CUST-'.uniqid(),
        'type' => 'customer',
    ]);

    $this->arAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'AR',
        'code' => 'AR-P6-'.uniqid(),
    ]);

    $this->salesAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Sales',
        'code' => 'SALES-P6-'.uniqid(),
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'customer_account_id' => $this->arAccount->id,
        'sales_account_id' => $this->salesAccount->id,
        'cash_sales_account_id' => $this->arAccount->id,
        'bank_sales_account_id' => $this->arAccount->id,
    ]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Test Product',
        'code' => 'PROD-P6-'.uniqid(),
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'sku' => 'SKU-P6-'.uniqid(),
        'sales_price' => 100,
        'is_default' => true,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Main',
        'code' => 'WH-P6-'.uniqid(),
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

// ─── Invoice void via API ─────────────────────────────────────────────────────

it('voiding an approved invoice via API stamps voided_at and creates a VOID GL contra-entry', function () {
    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'invoice_no' => 'INV-P6-VOID-001',
        'invoice_date' => '2024-09-01',
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    // Manually post an invoice GL journal (no items = no inventory to reverse)
    $journal = Journal::withoutGlobalScopes()->create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'type' => JournalTypeEnum::INVOICE,
        'reference_type' => $invoice->getMorphClass(),
        'reference_id' => $invoice->id,
        'voucher_no' => 'SALE-JV-P6-'.$invoice->id,
        'date' => '2024-09-01',
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED,
    ]);

    JournalItem::create([
        'journal_id' => $journal->id,
        'account_id' => $this->arAccount->id,
        'dr_amount' => 1000, 'cr_amount' => 0,
    ]);
    JournalItem::create([
        'journal_id' => $journal->id,
        'account_id' => $this->salesAccount->id,
        'dr_amount' => 0, 'cr_amount' => 1000,
    ]);

    $response = $this->postJson("/api/admin/invoice/{$invoice->id}/void");

    $response->assertOk();

    expect($invoice->fresh()->voided_at)->not->toBeNull();

    // Original INVOICE journal must be soft-deleted
    $liveInvoiceJournals = Journal::withoutGlobalScopes()
        ->where('type', JournalTypeEnum::INVOICE->value)
        ->where('reference_id', $invoice->id)
        ->whereNull('deleted_at')
        ->count();
    expect($liveInvoiceJournals)->toBe(0);

    // A VOID contra-entry must exist with swapped DR/CR items
    $voidJournal = Journal::withoutGlobalScopes()
        ->where('type', JournalTypeEnum::VOID->value)
        ->where('reference_type', $invoice->getMorphClass())
        ->where('reference_id', $invoice->id)
        ->whereNull('deleted_at')
        ->first();
    expect($voidJournal)->not->toBeNull();

    $items = $voidJournal->journalItems()->get();
    expect($items)->toHaveCount(2);
    expect((float) $items->sum('dr_amount'))->toBe((float) $items->sum('cr_amount'));
});

it('voiding an already-voided invoice returns a 200 without creating additional journals', function () {
    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'invoice_no' => 'INV-P6-VOID-002',
        'invoice_date' => '2024-09-01',
        'status' => StatusEnum::APPROVED,
        'voided_at' => now(),
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    $response = $this->postJson("/api/admin/invoice/{$invoice->id}/void");

    $response->assertOk();
    expect($response->json('message'))->toContain('voided');
    expect(Journal::withoutGlobalScopes()->where('type', JournalTypeEnum::VOID->value)->count())->toBe(0);
});

// ─── Cumulative credit note quantity guard ────────────────────────────────────

it('rejects a second credit note when cumulative quantity would exceed the original invoice item quantity', function () {
    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'invoice_no' => 'INV-P6-CN-001',
        'invoice_date' => '2024-09-01',
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    $invoiceItem = InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 5,
        'rate' => 100,
        'discount_amount' => 0,
        'tax_amount' => 0,
    ]);

    // First approved credit note: 3 units returned
    $firstCn = CreditNote::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'invoice_id' => $invoice->id,
        'credit_note_no' => 'CN-P6-001',
        'credit_note_date' => '2024-10-01',
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    CreditNoteItem::create([
        'credit_note_id' => $firstCn->id,
        'invoice_item_id' => $invoiceItem->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 3,
        'rate' => 100,
        'discount_amount' => 0,
        'tax_amount' => 0,
    ]);

    // Second credit note requests 3 more — only 2 remain (5 - 3 = 2)
    $response = $this->postJson('/api/admin/credit-note', [
        'credit_note_date' => '2024-11-01',
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
    ]);

    $response->assertStatus(422);
    $message = $response->json('errors.items.0.quantity.0') ?? $response->json('message');
    expect($message)->toContain('2'); // remaining = 2
});

it('accepts a second credit note when cumulative quantity exactly equals what remains', function () {
    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'invoice_no' => 'INV-P6-CN-002',
        'invoice_date' => '2024-09-01',
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    $invoiceItem = InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 5,
        'rate' => 100,
        'discount_amount' => 0,
        'tax_amount' => 0,
    ]);

    $firstCn = CreditNote::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'invoice_id' => $invoice->id,
        'credit_note_no' => 'CN-P6-002',
        'credit_note_date' => '2024-10-01',
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    CreditNoteItem::create([
        'credit_note_id' => $firstCn->id,
        'invoice_item_id' => $invoiceItem->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 3,
        'rate' => 100,
        'discount_amount' => 0,
        'tax_amount' => 0,
    ]);

    // Second credit note with exactly the remaining 2 units
    $response = $this->postJson('/api/admin/credit-note', [
        'credit_note_date' => '2024-11-01',
        'party_id' => $this->party->id,
        'invoice_id' => $invoice->id,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'invoice_item_id' => $invoiceItem->id,
            'quantity' => 2,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
        ]],
    ]);

    $response->assertSuccessful();
});

// ─── Branch isolation in dueInvoices ─────────────────────────────────────────

it('dueInvoices excludes invoices belonging to a different branch when branch scope is active', function () {
    $branchA = Branch::create([
        'company_id' => $this->company->id,
        'name' => 'Branch A',
        'code' => 'BRA-P6-'.uniqid(),
        'is_head_office' => true,
        'is_active' => true,
    ]);

    $branchB = Branch::create([
        'company_id' => $this->company->id,
        'name' => 'Branch B',
        'code' => 'BRB-P6-'.uniqid(),
        'is_head_office' => false,
        'is_active' => true,
    ]);

    Invoice::withoutGlobalScopes()->create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'branch_id' => $branchB->id,
        'invoice_no' => 'INV-P6-BRB-001',
        'invoice_date' => '2024-09-01',
        'total_amount' => 5000,
        'paid_amount' => 0,
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    // Scope the session to Branch A — Branch B's invoice must be invisible
    TenantService::setBranchId($branchA->id);

    $response = $this->getJson('/api/admin/invoice/due?party_id='.$this->party->id);

    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
});

it('dueInvoices shows invoices that belong to the active branch', function () {
    $branchA = Branch::create([
        'company_id' => $this->company->id,
        'name' => 'Branch A2',
        'code' => 'BRA2-P6-'.uniqid(),
        'is_head_office' => true,
        'is_active' => true,
    ]);

    Invoice::withoutGlobalScopes()->create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'branch_id' => $branchA->id,
        'invoice_no' => 'INV-P6-BRA-001',
        'invoice_date' => '2024-09-01',
        'total_amount' => 3000,
        'paid_amount' => 0,
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    TenantService::setBranchId($branchA->id);

    $response = $this->getJson('/api/admin/invoice/due?party_id='.$this->party->id);

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.invoice_no'))->toBe('INV-P6-BRA-001');
});
