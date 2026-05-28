<?php

use App\Models\Bill;
use App\Models\User;
use App\Models\Party;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Product;
use App\Enums\StatusEnum;
use App\Models\DebitNote;
use App\Models\CreditNote;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\InvoiceItem;
use App\Enums\PartyTypeEnum;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function docUniqueWarmAllTablesCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $tables[$table] = Schema::getColumnListing($table);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

beforeEach(function () {
    docUniqueWarmAllTablesCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);
    $this->fiscalYear2 = FiscalYear::create([
        'year_name' => '2027', 'year_code' => '27',
        'start_date' => '2027-01-01', 'end_date' => '2027-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Unique Co', 'code' => 'UQC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Unique Admin',
        'email' => 'uq-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->customer = Party::create([
        'company_id' => $this->company->id, 'name' => 'Customer', 'code' => 'C-UQ',
        'type' => PartyTypeEnum::CUSTOMER,
    ]);
    $this->supplier = Party::create([
        'company_id' => $this->company->id, 'name' => 'Supplier', 'code' => 'S-UQ',
        'type' => PartyTypeEnum::SUPPLIER,
    ]);

    $product = Product::create([
        'company_id' => $this->company->id, 'name' => 'Widget', 'code' => 'W-UQ',
    ]);
    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id, 'product_id' => $product->id,
        'sku' => 'SKU-UQ', 'sales_price' => 100, 'is_default' => true,
    ]);

    TenantService::setCompanyId($this->company->id);
});

function makeInvoice(object $test, string $no, ?int $fyId = null): Invoice
{
    return Invoice::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $fyId ?? $test->fiscalYear->id,
        'invoice_no' => $no,
        'invoice_date' => '2026-03-10',
        'party_id' => $test->customer->id,
        'create_user_id' => $test->user->id,
        'status' => StatusEnum::DRAFT,
    ]);
}

it('rejects duplicate invoice numbers within (company, fiscal year)', function () {
    makeInvoice($this, 'INV-UQ-1');

    expect(fn () => makeInvoice($this, 'INV-UQ-1'))
        ->toThrow(QueryException::class);
});

it('allows reusing an invoice number in a different fiscal year', function () {
    makeInvoice($this, 'INV-UQ-2');

    $other = makeInvoice($this, 'INV-UQ-2', fyId: $this->fiscalYear2->id);
    expect($other->id)->toBeInt();
});

it('also blocks reuse after a soft-delete (numbers are consumed for the audit trail)', function () {
    $invoice = makeInvoice($this, 'INV-UQ-3');
    $invoice->delete(); // soft-delete

    expect(fn () => makeInvoice($this, 'INV-UQ-3'))
        ->toThrow(QueryException::class);
});

it('rejects duplicate bill numbers within (company, fiscal year)', function () {
    Bill::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'bill_no' => 'BILL-UQ-1',
        'bill_date' => '2026-03-10',
        'party_id' => $this->supplier->id,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    expect(fn () => Bill::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'bill_no' => 'BILL-UQ-1',
        'bill_date' => '2026-03-11',
        'party_id' => $this->supplier->id,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]))->toThrow(QueryException::class);
});

it('rejects duplicate credit-note numbers within (company, fiscal year)', function () {
    $invoice = makeInvoice($this, 'INV-CN-BASE');
    InvoiceItem::create([
        'invoice_id' => $invoice->id, 'product_variant_id' => $this->variant->id,
        'quantity' => 1, 'rate' => 100, 'discount_amount' => 0, 'tax_amount' => 0, 'tax_line_type' => 'taxable',
    ]);

    CreditNote::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'credit_note_no' => 'CN-UQ-1',
        'credit_note_date' => '2026-03-12',
        'invoice_id' => $invoice->id,
        'party_id' => $this->customer->id,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    expect(fn () => CreditNote::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'credit_note_no' => 'CN-UQ-1',
        'credit_note_date' => '2026-03-13',
        'invoice_id' => $invoice->id,
        'party_id' => $this->customer->id,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]))->toThrow(QueryException::class);
});

it('rejects duplicate debit-note numbers within (company, fiscal year)', function () {
    $bill = Bill::create([
        'company_id' => $this->company->id, 'fiscal_year_id' => $this->fiscalYear->id,
        'bill_no' => 'BILL-DN-BASE', 'bill_date' => '2026-03-10',
        'party_id' => $this->supplier->id, 'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    DebitNote::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'debit_note_no' => 'DN-UQ-1',
        'debit_note_date' => '2026-03-12',
        'bill_id' => $bill->id,
        'party_id' => $this->supplier->id,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    expect(fn () => DebitNote::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'debit_note_no' => 'DN-UQ-1',
        'debit_note_date' => '2026-03-13',
        'bill_id' => $bill->id,
        'party_id' => $this->supplier->id,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]))->toThrow(QueryException::class);
});

it('rejects duplicate expense numbers within (company, fiscal year)', function () {
    Expense::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->supplier->id,
        'expense_no' => 'EXP-UQ-1',
        'date' => '2026-03-10',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    expect(fn () => Expense::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->supplier->id,
        'expense_no' => 'EXP-UQ-1',
        'date' => '2026-03-11',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]))->toThrow(QueryException::class);
});
