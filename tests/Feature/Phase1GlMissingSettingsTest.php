<?php

use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\Product;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\InvoiceItem;
use App\Enums\PartyTypeEnum;
use App\Models\AccountGroup;
use Laravel\Sanctum\Sanctum;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use App\Services\Accounting\InvoiceGlPostingService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function glMissingWarmCache(): void
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
    glMissingWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2081-82', 'year_code' => '8182',
        'start_date' => '2024-07-17', 'end_date' => '2025-07-16', 'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'GL Test Co', 'code' => 'GLC',
        'inventory_costing_method' => 'fifo',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Tester', 'email' => 'tester@gltest.com',
        'password' => bcrypt('secret'), 'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->customer = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Test Customer', 'code' => 'CUST-GLC', 'type' => PartyTypeEnum::CUSTOMER,
    ]);

    $product = Product::create([
        'company_id' => $this->company->id, 'name' => 'Widget', 'code' => 'WDGT-GLC',
    ]);
    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id, 'product_id' => $product->id,
        'sku' => 'SKU-GLC-1', 'sales_price' => 100, 'is_default' => true,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

function makeApprovedInvoiceWithLineItems(object $test, float $rate = 100, float $taxAmount = 0, string $taxLineType = 'exempt'): Invoice
{
    $invoice = Invoice::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'invoice_no' => 'INV-BUG001-'.uniqid(),
        'invoice_date' => now()->toDateString(),
        'party_id' => $test->customer->id,
        'create_user_id' => $test->user->id,
        'approve_user_id' => $test->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_variant_id' => $test->variant->id,
        'quantity' => 1,
        'rate' => $rate,
        'discount_amount' => 0,
        'tax_amount' => $taxAmount,
        'tax_line_type' => $taxLineType,
    ]);

    return $invoice;
}

it('throws ValidationException when account settings are entirely missing — BUG-001', function () {
    $invoice = makeApprovedInvoiceWithLineItems($this);

    expect(fn () => app(InvoiceGlPostingService::class)->postFromInvoice($invoice))
        ->toThrow(ValidationException::class);
});

it('throws ValidationException when sales account is not configured — BUG-001', function () {
    $group = AccountGroup::create(['company_id' => $this->company->id, 'name' => 'Assets', 'code' => 'ASSETS-A']);
    $arAccount = Account::create(['company_id' => $this->company->id, 'account_group_id' => $group->id, 'name' => 'AR', 'code' => 'AR-001', 'is_active' => true]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'customer_account_id' => $arAccount->id,
        'sales_account_id' => null,
    ]);

    $invoice = makeApprovedInvoiceWithLineItems($this);

    expect(fn () => app(InvoiceGlPostingService::class)->postFromInvoice($invoice))
        ->toThrow(ValidationException::class);
});

it('throws ValidationException when VAT account is missing and invoice has tax — BUG-001', function () {
    $group = AccountGroup::create(['company_id' => $this->company->id, 'name' => 'Assets', 'code' => 'ASSETS-B']);
    $arAccount = Account::create(['company_id' => $this->company->id, 'account_group_id' => $group->id, 'name' => 'AR', 'code' => 'AR-002', 'is_active' => true]);
    $salesAccount = Account::create(['company_id' => $this->company->id, 'account_group_id' => $group->id, 'name' => 'Sales', 'code' => 'SAL-002', 'is_active' => true]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'customer_account_id' => $arAccount->id,
        'sales_account_id' => $salesAccount->id,
        'vat_account_id' => null,
    ]);

    $invoice = makeApprovedInvoiceWithLineItems($this, rate: 100, taxAmount: 13, taxLineType: 'taxable');

    expect(fn () => app(InvoiceGlPostingService::class)->postFromInvoice($invoice))
        ->toThrow(ValidationException::class);
});

it('posts GL journal successfully when all required accounts are configured', function () {
    $group = AccountGroup::create(['company_id' => $this->company->id, 'name' => 'Assets', 'code' => 'ASSETS-OK']);
    $arAccount = Account::create(['company_id' => $this->company->id, 'account_group_id' => $group->id, 'name' => 'AR', 'code' => 'AR-OK', 'is_active' => true]);
    $salesAccount = Account::create(['company_id' => $this->company->id, 'account_group_id' => $group->id, 'name' => 'Sales', 'code' => 'SAL-OK', 'is_active' => true]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'customer_account_id' => $arAccount->id,
        'sales_account_id' => $salesAccount->id,
    ]);

    $invoice = makeApprovedInvoiceWithLineItems($this);
    app(InvoiceGlPostingService::class)->postFromInvoice($invoice);

    expect(
        Journal::withoutGlobalScopes()
            ->where('reference_type', $invoice->getMorphClass())
            ->where('reference_id', $invoice->id)
            ->exists()
    )->toBeTrue();
});
