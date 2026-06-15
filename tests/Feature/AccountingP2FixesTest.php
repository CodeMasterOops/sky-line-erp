<?php

use App\Models\Bill;
use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\BillItem;
use App\Enums\StatusEnum;
use App\Models\DebitNote;
use App\Models\Warehouse;
use App\Models\CreditNote;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\InvoiceItem;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Services\TenantService;
use App\Models\PaymentAllocation;
use App\Models\ReceiptAllocation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use App\Services\Accounting\DebitNoteGlPostingService;
use App\Services\Accounting\CreditNoteGlPostingService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function accountP2WarmCache(): void
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
    accountP2WarmCache();

    $this->fy = FiscalYear::create([
        'year_name' => 'FY-P2', 'year_code' => 'P2',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);
    $this->company = Company::create([
        'fiscal_year_id' => $this->fy->id,
        'company_name' => 'P2 Co', 'code' => 'COP2',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);
    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'P2 User',
        'email' => 'p2-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);
    $this->account = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'GL-P2', 'code' => 'GLP2', 'is_active' => true,
    ]);
    $this->party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Test Party P2', 'code' => 'PARTY-P2',
        'type' => PartyTypeEnum::CUSTOMER,
        'pan' => '987654321',
    ]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Widget P2', 'code' => 'WID-P2',
    ]);
    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'sku' => 'SKU-P2', 'sales_price' => 100, 'is_default' => true,
    ]);
    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Main WH P2', 'code' => 'WH-P2',
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'customer_account_id' => $this->account->id,
        'sales_account_id' => $this->account->id,
        'supplier_account_id' => $this->account->id,
        'purchase_account_id' => $this->account->id,
        'vat_account_id' => $this->account->id,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

// ─── P2.1: AR Aging — pre-aggregated allocations (no N+1) ────────────────────

it('arAging returns correct outstanding after partial receipt allocation', function () {
    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fy->id,
        'party_id' => $this->party->id,
        'invoice_no' => 'INV-P2-001',
        'invoice_date' => '2026-06-01',
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
        'rate' => 1000,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'tax_line_type' => 'zero_rated',
    ]);

    $receipt = Receipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fy->id,
        'party_id' => $this->party->id,
        'receipt_no' => 'REC-P2-001',
        'receipt_date' => '2026-06-01',
        'payment_method' => 'cash',
        'account_id' => $this->account->id,
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
    ]);
    ReceiptAllocation::create([
        'receipt_id' => $receipt->id,
        'invoice_id' => $invoice->id,
        'amount' => 400,
    ]);

    $response = $this->getJson('/api/admin/account-report/ar-aging');

    $response->assertOk();
    $rows = collect($response->json('data.rows'));
    $invoiceRow = $rows->firstWhere('invoice_no', 'INV-P2-001');

    expect($invoiceRow)->not->toBeNull()
        ->and((float) $invoiceRow['outstanding'])->toBe(600.0);
});

it('arAging excludes invoices that are fully paid', function () {
    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fy->id,
        'party_id' => $this->party->id,
        'invoice_no' => 'INV-P2-PAID',
        'invoice_date' => '2026-06-01',
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
        'rate' => 500,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'tax_line_type' => 'zero_rated',
    ]);

    $receipt = Receipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fy->id,
        'party_id' => $this->party->id,
        'receipt_no' => 'REC-P2-PAID',
        'receipt_date' => '2026-06-01',
        'payment_method' => 'cash',
        'account_id' => $this->account->id,
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
    ]);
    ReceiptAllocation::create([
        'receipt_id' => $receipt->id,
        'invoice_id' => $invoice->id,
        'amount' => 500,
    ]);

    $response = $this->getJson('/api/admin/account-report/ar-aging');

    $response->assertOk();
    $rows = collect($response->json('data.rows'));

    // Fully paid invoice must not appear in aging.
    expect($rows->firstWhere('invoice_no', 'INV-P2-PAID'))->toBeNull();
});

// ─── P2.1: AP Aging — pre-aggregated allocations (no N+1) ────────────────────

it('apAging returns correct outstanding after partial payment allocation', function () {
    $supplier = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Supplier P2', 'code' => 'SUP-P2',
        'type' => PartyTypeEnum::SUPPLIER,
    ]);

    $bill = Bill::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fy->id,
        'party_id' => $supplier->id,
        'bill_no' => 'BILL-P2-001',
        'bill_date' => '2026-06-01',
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    BillItem::create([
        'bill_id' => $bill->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
        'rate' => 800,
        'discount_amount' => 0,
        'tax_amount' => 0,
    ]);

    $payment = Payment::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fy->id,
        'party_id' => $supplier->id,
        'payment_no' => 'PAY-P2-001',
        'payment_date' => '2026-06-01',
        'account_id' => $this->account->id,
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
    ]);
    // Bill uses MorphMany — payable_type must match Bill's morph class.
    PaymentAllocation::create([
        'payment_id' => $payment->id,
        'payable_type' => $bill->getMorphClass(),
        'payable_id' => $bill->id,
        'amount' => 300,
    ]);

    $response = $this->getJson('/api/admin/account-report/ap-aging');

    $response->assertOk();
    $rows = collect($response->json('data.rows'));
    $billRow = $rows->firstWhere('bill_no', 'BILL-P2-001');

    expect($billRow)->not->toBeNull()
        ->and((float) $billRow['outstanding'])->toBe(500.0);
});

// ─── P2.3/P2.4: GL posting services throw on missing company/fiscal year ──────

it('CreditNoteGlPostingService throws RuntimeException when company has no fiscal year', function () {
    $brokenCompany = Company::create([
        'fiscal_year_id' => null,
        'company_name' => 'Broken Co CN', 'code' => 'BRKNCN',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    // AccountSetting required so the service reaches the user/company checks.
    AccountSetting::create([
        'company_id' => $brokenCompany->id,
        'customer_account_id' => $this->account->id,
        'sales_account_id' => $this->account->id,
    ]);

    $creditNote = CreditNote::create([
        'company_id' => $brokenCompany->id,
        'fiscal_year_id' => $this->fy->id,
        'party_id' => $this->party->id,
        'credit_note_no' => 'CN-BROKEN-001',
        'credit_note_date' => '2026-06-01',
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    $creditNote->creditNoteItems()->create([
        'product_variant_id' => $this->variant->id,
        'quantity' => 1, 'rate' => 100,
        'discount_amount' => 0, 'tax_amount' => 0,
        'tax_line_type' => 'zero_rated',
    ]);

    $service = app(CreditNoteGlPostingService::class);

    // No user for brokenCompany → RuntimeException (not silent return).
    expect(fn () => $service->postFromCreditNote($creditNote))
        ->toThrow(\RuntimeException::class);
});

it('DebitNoteGlPostingService throws RuntimeException when company has no fiscal year', function () {
    $brokenCompany = Company::create([
        'fiscal_year_id' => null,
        'company_name' => 'Broken Co DN', 'code' => 'BRKDN',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    // AccountSetting required so the service reaches the user/company checks.
    AccountSetting::create([
        'company_id' => $brokenCompany->id,
        'supplier_account_id' => $this->account->id,
        'purchase_account_id' => $this->account->id,
    ]);

    $debitNote = DebitNote::create([
        'company_id' => $brokenCompany->id,
        'fiscal_year_id' => $this->fy->id,
        'party_id' => $this->party->id,
        'debit_note_no' => 'DN-BROKEN-001',
        'debit_note_date' => '2026-06-01',
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    $debitNote->debitNoteItems()->create([
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 1, 'rate' => 100,
        'discount_amount' => 0, 'tax_amount' => 0,
    ]);

    $service = app(DebitNoteGlPostingService::class);

    // No user for brokenCompany → RuntimeException (not silent return).
    expect(fn () => $service->postFromDebitNote($debitNote))
        ->toThrow(\RuntimeException::class);
});
