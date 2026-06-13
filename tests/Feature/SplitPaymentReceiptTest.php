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
use App\Enums\PartyTypeEnum;
use App\Models\AccountGroup;
use Laravel\Sanctum\Sanctum;
use App\Enums\ProductTypeEnum;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Models\ReceiptPayment;
use App\Enums\ChequeStatusEnum;
use App\Services\TenantService;
use App\Enums\PaymentMethodEnum;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function splitPayWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

function splitPaySetupAccounts(object $test): void
{
    $group = AccountGroup::create([
        'company_id' => $test->company->id,
        'name' => 'SplitPay GL '.uniqid(),
        'code' => 'SPGL-'.uniqid(),
    ]);
    $ar = Account::create([
        'company_id' => $test->company->id, 'account_group_id' => $group->id,
        'name' => 'AR SplitPay', 'code' => 'AR-SP-'.uniqid(), 'is_active' => true,
    ]);
    $test->cashAccount = Account::create([
        'company_id' => $test->company->id, 'account_group_id' => $group->id,
        'name' => 'Cash SP', 'code' => 'CASH-SP-'.uniqid(), 'is_active' => true,
    ]);
    $test->bankAccount = Account::create([
        'company_id' => $test->company->id, 'account_group_id' => $group->id,
        'name' => 'Bank SP', 'code' => 'BANK-SP-'.uniqid(), 'is_active' => true,
    ]);
    AccountSetting::create([
        'company_id' => $test->company->id,
        'customer_account_id' => $ar->id,
        'sales_account_id' => $ar->id,
        'cash_sales_account_id' => $ar->id,
        'bank_sales_account_id' => $ar->id,
    ]);
}

function splitPayCreateApprovedInvoice(object $test, float $amount = 1000.0): Invoice
{
    $product = Product::create([
        'company_id' => $test->company->id,
        'name' => 'Svc-'.uniqid(),
        'code' => 'SVC-'.uniqid(),
        'product_type' => ProductTypeEnum::SERVICE,
    ]);

    $variant = ProductVariant::create([
        'company_id' => $test->company->id,
        'product_id' => $product->id,
        'sku' => 'SPSVC-'.uniqid(),
        'sales_price' => $amount,
        'is_default' => true,
    ]);

    $invoice = Invoice::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'party_id' => $test->party->id,
        'invoice_no' => 'INV-SP-'.uniqid(),
        'invoice_date' => now()->toDateString(),
        'create_user_id' => $test->user->id,
        'approve_user_id' => $test->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_variant_id' => $variant->id,
        'quantity' => 1,
        'rate' => $amount,
        'tax_amount' => 0,
        'discount_amount' => 0,
    ]);

    return $invoice;
}

beforeEach(function () {
    splitPayWarmCache();
    TenantService::setCompanyId(null);
    TenantService::setBranchId(null);

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'SplitPay Co',
        'code' => 'SPC-'.uniqid(),
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'SplitPay Tester',
        'email' => 'sp-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'SP Customer',
        'code' => 'SPCUST-'.uniqid(),
        'type' => PartyTypeEnum::CUSTOMER,
    ]);

    TenantService::setCompanyId($this->company->id);
    Sanctum::actingAs($this->user, ['*'], 'admin');
});

// ─── PaymentMethodEnum and ChequeStatusEnum ───────────────────────────────────

it('PaymentMethodEnum has expected cases', function () {
    expect(PaymentMethodEnum::Cash->value)->toBe('cash');
    expect(PaymentMethodEnum::BankTransfer->value)->toBe('bank_transfer');
    expect(PaymentMethodEnum::Cheque->value)->toBe('cheque');
    expect(PaymentMethodEnum::Cheque->isCheque())->toBeTrue();
    expect(PaymentMethodEnum::Cash->isCheque())->toBeFalse();
});

it('ChequeStatusEnum has expected cases', function () {
    expect(ChequeStatusEnum::Pending->value)->toBe('pending');
    expect(ChequeStatusEnum::Cleared->value)->toBe('cleared');
    expect(ChequeStatusEnum::Bounced->value)->toBe('bounced');
});

// ─── Split payment receipt creation ──────────────────────────────────────────

it('creates receipt with split payments and stores both legs', function () {
    splitPaySetupAccounts($this);

    $invoice = splitPayCreateApprovedInvoice($this, 1000.0);

    $response = $this->postJson('/api/admin/receipt', [
        'receipt_date' => '2026-06-13',
        'party_id' => $this->party->id,
        'payments' => [
            [
                'payment_method' => 'cash',
                'account_id' => $this->cashAccount->id,
                'amount' => 400,
            ],
            [
                'payment_method' => 'bank_transfer',
                'account_id' => $this->bankAccount->id,
                'amount' => 600,
            ],
        ],
        'allocations' => [[
            'invoice_id' => $invoice->id,
            'amount' => 1000,
        ]],
        'status' => 'draft',
    ]);

    $response->assertSuccessful();

    $receiptId = $response->json('data.id');
    $legs = ReceiptPayment::where('receipt_id', $receiptId)->get();

    expect($legs)->toHaveCount(2);
    expect($legs->pluck('payment_method')->map(fn ($m) => $m->value)->toArray())
        ->toContain('cash')
        ->toContain('bank_transfer');
    expect((float) $legs->sum('amount'))->toBe(1000.0);
});

it('legacy single-leg receipt still works without payments array', function () {
    splitPaySetupAccounts($this);

    $invoice = splitPayCreateApprovedInvoice($this, 500.0);

    $response = $this->postJson('/api/admin/receipt', [
        'receipt_date' => '2026-06-13',
        'party_id' => $this->party->id,
        'payment_method' => 'cash',
        'account_id' => $this->cashAccount->id,
        'allocations' => [[
            'invoice_id' => $invoice->id,
            'amount' => 500,
        ]],
        'status' => 'draft',
    ]);

    $response->assertSuccessful();

    $receiptId = $response->json('data.id');
    $legs = ReceiptPayment::where('receipt_id', $receiptId)->get();
    expect($legs)->toHaveCount(1);
    expect($legs->first()->payment_method->value)->toBe('cash');
    expect((float) $legs->first()->amount)->toBe(500.0);
});

it('split payment receipt resource includes payments array', function () {
    splitPaySetupAccounts($this);

    $invoice = splitPayCreateApprovedInvoice($this, 800.0);

    $response = $this->postJson('/api/admin/receipt', [
        'receipt_date' => '2026-06-13',
        'party_id' => $this->party->id,
        'payments' => [
            ['payment_method' => 'cash', 'account_id' => $this->cashAccount->id, 'amount' => 300],
            ['payment_method' => 'bank_transfer', 'account_id' => $this->bankAccount->id, 'amount' => 500],
        ],
        'allocations' => [['invoice_id' => $invoice->id, 'amount' => 800]],
        'status' => 'draft',
    ]);

    $response->assertSuccessful();
    $payments = $response->json('data.payments');
    expect($payments)->toHaveCount(2);
    expect(collect($payments)->pluck('payment_method')->toArray())
        ->toContain('cash')
        ->toContain('bank_transfer');
});

// ─── Cheque tracking ──────────────────────────────────────────────────────────

it('creates cheque payment leg with pending status', function () {
    splitPaySetupAccounts($this);

    $invoice = splitPayCreateApprovedInvoice($this, 1000.0);

    $response = $this->postJson('/api/admin/receipt', [
        'receipt_date' => '2026-06-13',
        'party_id' => $this->party->id,
        'payments' => [[
            'payment_method' => 'cheque',
            'account_id' => $this->bankAccount->id,
            'amount' => 1000,
            'reference_no' => 'CHQ-001',
            'cheque_date' => '2026-06-20',
        ]],
        'allocations' => [['invoice_id' => $invoice->id, 'amount' => 1000]],
        'status' => 'draft',
    ]);

    $response->assertSuccessful();

    $leg = ReceiptPayment::where('receipt_id', $response->json('data.id'))->first();
    expect($leg->payment_method)->toBe(PaymentMethodEnum::Cheque);
    expect($leg->cheque_status)->toBe(ChequeStatusEnum::Pending);
    expect($leg->reference_no)->toBe('CHQ-001');
    expect($leg->cheque_date->toDateString())->toBe('2026-06-20');
});

it('can clear a cheque payment', function () {
    splitPaySetupAccounts($this);

    $invoice = splitPayCreateApprovedInvoice($this, 500.0);

    $receiptResponse = $this->postJson('/api/admin/receipt', [
        'receipt_date' => '2026-06-13',
        'party_id' => $this->party->id,
        'payments' => [[
            'payment_method' => 'cheque',
            'account_id' => $this->bankAccount->id,
            'amount' => 500,
            'cheque_date' => '2026-06-20',
        ]],
        'allocations' => [['invoice_id' => $invoice->id, 'amount' => 500]],
        'status' => 'draft',
    ]);

    $leg = ReceiptPayment::where('receipt_id', $receiptResponse->json('data.id'))->first();
    expect($leg->cheque_status)->toBe(ChequeStatusEnum::Pending);

    $clearResponse = $this->postJson("/api/admin/receipt-payment/{$leg->id}/clear-cheque");
    $clearResponse->assertOk();

    expect($leg->fresh()->cheque_status)->toBe(ChequeStatusEnum::Cleared);
});

it('cannot clear a non-cheque payment', function () {
    splitPaySetupAccounts($this);

    $invoice = splitPayCreateApprovedInvoice($this, 300.0);

    $receiptResponse = $this->postJson('/api/admin/receipt', [
        'receipt_date' => '2026-06-13',
        'party_id' => $this->party->id,
        'payments' => [[
            'payment_method' => 'cash',
            'account_id' => $this->cashAccount->id,
            'amount' => 300,
        ]],
        'allocations' => [['invoice_id' => $invoice->id, 'amount' => 300]],
        'status' => 'draft',
    ]);

    $leg = ReceiptPayment::where('receipt_id', $receiptResponse->json('data.id'))->first();

    $clearResponse = $this->postJson("/api/admin/receipt-payment/{$leg->id}/clear-cheque");
    $clearResponse->assertStatus(422);
});

it('cannot clear an already cleared cheque', function () {
    splitPaySetupAccounts($this);

    $invoice = splitPayCreateApprovedInvoice($this, 700.0);

    $receiptResponse = $this->postJson('/api/admin/receipt', [
        'receipt_date' => '2026-06-13',
        'party_id' => $this->party->id,
        'payments' => [[
            'payment_method' => 'cheque',
            'account_id' => $this->bankAccount->id,
            'amount' => 700,
            'cheque_date' => '2026-06-20',
        ]],
        'allocations' => [['invoice_id' => $invoice->id, 'amount' => 700]],
        'status' => 'draft',
    ]);

    $leg = ReceiptPayment::where('receipt_id', $receiptResponse->json('data.id'))->first();

    $this->postJson("/api/admin/receipt-payment/{$leg->id}/clear-cheque");
    $secondClear = $this->postJson("/api/admin/receipt-payment/{$leg->id}/clear-cheque");

    $secondClear->assertStatus(422);
});
