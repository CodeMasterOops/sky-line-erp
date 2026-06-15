<?php

use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Receipt;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\InvoiceItem;
use Laravel\Sanctum\Sanctum;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Services\TenantService;
use App\Models\ReceiptAllocation;
use Illuminate\Support\Facades\Cache;
use App\Services\Sales\InvoiceService;
use App\Services\Sales\ReceiptService;
use Illuminate\Support\Facades\Schema;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function totalsWarmCache(): void
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
    totalsWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2081-82',
        'year_code' => '8182',
        'start_date' => '2024-07-17',
        'end_date' => '2025-07-16',
        'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Totals Test Co',
        'code' => 'TTC-'.uniqid(),
        'inventory_costing_method' => 'fifo',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Totals Tester',
        'email' => 'totals-'.uniqid().'@test.com',
        'password' => bcrypt('secret'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Total Customer',
        'code' => 'TC-'.uniqid(),
        'type' => 'customer',
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Widget',
        'code' => 'WGT-'.uniqid(),
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'WGT-SKU-'.uniqid(),
        'sales_price' => 200,
        'is_default' => true,
    ]);

    $this->cashAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Cash',
        'code' => 'CASH-'.uniqid(),
    ]);

    $this->arAccount = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'AR',
        'code' => 'AR-'.uniqid(),
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'customer_account_id' => $this->arAccount->id,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

// ─── refreshTotals() unit ────────────────────────────────────────────────────

it('refreshTotals stores total_amount from invoice items', function () {
    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'invoice_no' => 'INV-TOTALS-001',
        'invoice_date' => '2024-10-01',
        'status' => StatusEnum::DRAFT,
        'create_user_id' => $this->user->id,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 3,
        'rate' => 100,
        'discount_amount' => 10,
        'tax_amount' => 20,
        'tax_line_type' => 'taxable',
    ]);

    $invoice->refreshTotals();

    // total = 3×100 - 10 + 20 = 310
    expect($invoice->fresh()->total_amount)->toBe(310.0);
});

it('refreshTotals paid_amount counts only approved receipt allocations', function () {
    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'invoice_no' => 'INV-TOTALS-002',
        'invoice_date' => '2024-10-01',
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    // Draft receipt allocation — should NOT count
    $draftReceipt = Receipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'receipt_no' => 'RC-DRAFT-001',
        'receipt_date' => '2024-10-01',
        'payment_method' => 'cash',
        'account_id' => $this->cashAccount->id,
        'status' => StatusEnum::DRAFT,
        'create_user_id' => $this->user->id,
    ]);
    ReceiptAllocation::create(['receipt_id' => $draftReceipt->id, 'invoice_id' => $invoice->id, 'amount' => 500]);

    // Approved receipt allocation — SHOULD count
    $approvedReceipt = Receipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'receipt_no' => 'RC-APPR-001',
        'receipt_date' => '2024-10-01',
        'payment_method' => 'cash',
        'account_id' => $this->cashAccount->id,
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);
    ReceiptAllocation::create(['receipt_id' => $approvedReceipt->id, 'invoice_id' => $invoice->id, 'amount' => 300]);

    $invoice->refreshTotals();

    expect($invoice->fresh()->paid_amount)->toBe(300.0);
});

// ─── InvoiceService integration ──────────────────────────────────────────────

it('InvoiceService::createInvoice sets total_amount', function () {
    $invoice = app(InvoiceService::class)->createInvoice([
        'invoice_date' => '2024-10-01',
        'party_id' => $this->party->id,
        'status' => StatusEnum::DRAFT->value,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'quantity' => 2,
            'rate' => 150,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'tax_line_type' => 'taxable',
        ]],
    ]);

    // total = 2 × 150 = 300
    expect($invoice->fresh()->total_amount)->toBe(300.0);
    expect($invoice->fresh()->paid_amount)->toBe(0.0);
});

it('InvoiceService::updateInvoice recalculates total_amount', function () {
    $invoice = app(InvoiceService::class)->createInvoice([
        'invoice_date' => '2024-10-01',
        'party_id' => $this->party->id,
        'status' => StatusEnum::DRAFT->value,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'quantity' => 1,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'tax_line_type' => 'taxable',
        ]],
    ]);

    expect($invoice->fresh()->total_amount)->toBe(100.0);

    app(InvoiceService::class)->updateInvoice([
        'invoice_date' => '2024-10-01',
        'party_id' => $this->party->id,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'quantity' => 5,
            'rate' => 200,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'tax_line_type' => 'taxable',
        ]],
    ], $invoice);

    // total = 5 × 200 = 1,000
    expect($invoice->fresh()->total_amount)->toBe(1000.0);
});

// ─── ReceiptService integration ───────────────────────────────────────────────

it('ReceiptService::createReceipt approved updates invoice paid_amount', function () {
    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'invoice_no' => 'INV-PAY-001',
        'invoice_date' => '2024-10-01',
        'total_amount' => 1000,
        'paid_amount' => 0,
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    app(ReceiptService::class)->createReceipt([
        'party_id' => $this->party->id,
        'receipt_date' => '2024-10-15',
        'payment_method' => 'cash',
        'account_id' => $this->cashAccount->id,
        'status' => StatusEnum::APPROVED->value,
        'allocations' => [
            ['invoice_id' => $invoice->id, 'amount' => 600],
        ],
    ]);

    expect($invoice->fresh()->paid_amount)->toBe(600.0);
});

it('ReceiptService::approveReceipt updates invoice paid_amount', function () {
    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'invoice_no' => 'INV-PAY-002',
        'invoice_date' => '2024-10-01',
        'total_amount' => 1000,
        'paid_amount' => 0,
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    $service = app(ReceiptService::class);

    $receipt = $service->createReceipt([
        'party_id' => $this->party->id,
        'receipt_date' => '2024-10-15',
        'payment_method' => 'cash',
        'account_id' => $this->cashAccount->id,
        'status' => StatusEnum::DRAFT->value,
        'allocations' => [
            ['invoice_id' => $invoice->id, 'amount' => 400],
        ],
    ]);

    // Still 0 — receipt is draft
    expect($invoice->fresh()->paid_amount)->toBe(0.0);

    $service->approveReceipt($receipt);

    expect($invoice->fresh()->paid_amount)->toBe(400.0);
});

it('ReceiptService::voidReceipt clears paid_amount on invoice', function () {
    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->party->id,
        'invoice_no' => 'INV-VOID-001',
        'invoice_date' => '2024-10-01',
        'total_amount' => 1000,
        'paid_amount' => 0,
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    $service = app(ReceiptService::class);

    $receipt = $service->createReceipt([
        'party_id' => $this->party->id,
        'receipt_date' => '2024-10-15',
        'payment_method' => 'cash',
        'account_id' => $this->cashAccount->id,
        'status' => StatusEnum::APPROVED->value,
        'allocations' => [
            ['invoice_id' => $invoice->id, 'amount' => 750],
        ],
    ]);

    expect($invoice->fresh()->paid_amount)->toBe(750.0);

    $service->voidReceipt($receipt);

    expect($invoice->fresh()->paid_amount)->toBe(0.0);
});
