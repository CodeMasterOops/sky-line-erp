<?php

use App\Models\Bill;
use App\Models\User;
use App\Models\Journal;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Warehouse;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Enums\JournalTypeEnum;
use App\Models\GoodsReceivedNote;
use App\Enums\InventoryCostingMethodEnum;
use App\Services\DocumentNumberGenerator;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2081',
        'year_code' => '81',
        'start_date' => '2024-04-01',
        'end_date' => '2025-03-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Doc Num Test Co',
        'code' => 'DNTC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Doc Tester',
        'email' => 'doc-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Main',
        'code' => 'WH-DOC',
    ]);

    $this->generator = app(DocumentNumberGenerator::class);
});

it('generates fiscal year document numbers with year suffix', function () {
    expect($this->generator->fiscalYear(
        Invoice::class,
        'INV-',
        $this->fiscalYear->id,
        $this->fiscalYear->year_code,
    ))->toBe('INV-1/81');

    Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'invoice_no' => 'INV-1/81',
        'invoice_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT->value,
    ]);

    expect($this->generator->fiscalYear(
        Bill::class,
        'BILL-',
        $this->fiscalYear->id,
        $this->fiscalYear->year_code,
    ))->toBe('BILL-1/81');
});

it('generates company padded document numbers', function () {
    expect($this->generator->companyPadded(
        GoodsReceivedNote::class,
        'GRN-',
        $this->company->id,
    ))->toBe('GRN-00001');

    GoodsReceivedNote::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'warehouse_id' => $this->warehouse->id,
        'grn_no' => 'GRN-00001',
        'received_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
    ]);

    expect($this->generator->companyPadded(
        GoodsReceivedNote::class,
        'GRN-',
        $this->company->id,
    ))->toBe('GRN-00002');
});

it('generates production order numbers with date segment', function () {
    $orderNo = $this->generator->productionOrder($this->company->id);

    expect($orderNo)->toMatch('/^PO-\d{6}-0001$/');
});

it('scopes journal voucher numbers by type within fiscal year', function () {
    Journal::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'type' => JournalTypeEnum::JOURNAL_VOUCHER->value,
        'voucher_no' => 'JV-1/81',
        'date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT->value,
    ]);

    expect($this->generator->journalVoucher(
        JournalTypeEnum::JOURNAL_VOUCHER,
        'JV-',
        $this->fiscalYear->id,
        $this->fiscalYear->year_code,
    ))->toBe('JV-2/81')
        ->and($this->generator->journalVoucher(
            JournalTypeEnum::PAYMENT_VOUCHER,
            'PV-',
            $this->fiscalYear->id,
            $this->fiscalYear->year_code,
        ))->toBe('PV-1/81');
});

it('increments fiscal year numbers after soft deleted records', function () {
    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'invoice_no' => 'INV-1/81',
        'invoice_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT->value,
    ]);
    $invoice->delete();

    expect($this->generator->fiscalYear(
        Invoice::class,
        'INV-',
        $this->fiscalYear->id,
        $this->fiscalYear->year_code,
    ))->toBe('INV-2/81');
});
