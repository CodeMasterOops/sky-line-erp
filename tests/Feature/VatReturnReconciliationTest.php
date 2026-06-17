<?php

use App\Models\Bill;
use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\Product;
use App\Models\BillItem;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\InvoiceItem;
use App\Models\JournalItem;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Enums\JournalTypeEnum;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function vatReconWarmAllTablesCache(): void
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
    vatReconWarmAllTablesCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'VAT Co', 'code' => 'VATC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'VAT Admin',
        'email' => 'vat-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->customer = Party::create([
        'company_id' => $this->company->id, 'name' => 'Customer', 'code' => 'C-VAT',
        'type' => PartyTypeEnum::CUSTOMER,
    ]);
    $this->supplier = Party::create([
        'company_id' => $this->company->id, 'name' => 'Supplier', 'code' => 'S-VAT',
        'type' => PartyTypeEnum::SUPPLIER,
    ]);

    $product = Product::create([
        'company_id' => $this->company->id, 'name' => 'Widget', 'code' => 'WIDGET-VAT',
    ]);
    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id, 'product_id' => $product->id,
        'sku' => 'SKU-VAT-1', 'sales_price' => 100, 'is_default' => true,
    ]);

    $this->vatAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'VAT', 'code' => 'VAT-ACC',
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'vat_account_id' => $this->vatAccount->id,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

function makeTaxedInvoice(object $test, string $no, float $taxable, float $vat): Invoice
{
    $invoice = Invoice::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'invoice_no' => $no,
        'invoice_date' => '2026-03-10',
        'party_id' => $test->customer->id,
        'create_user_id' => $test->user->id,
        'status' => StatusEnum::APPROVED,
        'approved_at' => now(),
    ]);
    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_variant_id' => $test->variant->id,
        'quantity' => 1, 'rate' => $taxable, 'discount_amount' => 0,
        'tax_amount' => $vat, 'tax_line_type' => 'taxable',
    ]);

    return $invoice;
}

function makeTaxedBill(object $test, string $no, float $taxable, float $vat): Bill
{
    $bill = Bill::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'bill_no' => $no,
        'bill_date' => '2026-03-12',
        'party_id' => $test->supplier->id,
        'create_user_id' => $test->user->id,
        'status' => StatusEnum::APPROVED,
        'approved_at' => now(),
    ]);
    BillItem::create([
        'bill_id' => $bill->id,
        'product_variant_id' => $test->variant->id,
        'quantity' => 1, 'rate' => $taxable, 'discount_amount' => 0,
        'tax_amount' => $vat, 'tax_line_type' => 'taxable',
    ]);

    return $bill;
}

function postVatJournal(object $test, float $cr, float $dr, string $voucher): void
{
    $journal = Journal::withoutGlobalScopes()->create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'type' => JournalTypeEnum::JOURNAL_VOUCHER,
        'voucher_no' => $voucher,
        'date' => '2026-03-12',
        'create_user_id' => $test->user->id,
        'status' => StatusEnum::APPROVED,
    ]);
    JournalItem::create([
        'journal_id' => $journal->id, 'account_id' => $test->vatAccount->id,
        'dr_amount' => $dr, 'cr_amount' => $cr,
    ]);
}

it('reconciles computed VAT against the GL VAT account', function () {
    makeTaxedInvoice($this, 'INV-VAT-1', 1000, 130);  // output VAT 130
    makeTaxedBill($this, 'BILL-VAT-1', 500, 65);       // input VAT 65
    // GL: output VAT credited 130, input VAT debited 65 → net 65.
    postVatJournal($this, cr: 130, dr: 0, voucher: 'JV-OUT-1');
    postVatJournal($this, cr: 0, dr: 65, voucher: 'JV-IN-1');

    $response = $this->getJson('/api/admin/account-report/vat-return-reconciliation');

    $response->assertSuccessful()
        ->assertJsonPath('data.account_configured', true)
        ->assertJsonPath('data.output_vat', 130)
        ->assertJsonPath('data.input_vat', 65)
        ->assertJsonPath('data.computed_net_vat', 65)
        ->assertJsonPath('data.gl_net_vat', 65)
        ->assertJsonPath('data.delta', 0)
        ->assertJsonPath('data.reconciled', true);
});

it('shows a delta when an invoice VAT is not posted to the GL', function () {
    makeTaxedInvoice($this, 'INV-VAT-2', 1000, 130); // output VAT 130, no journal

    $response = $this->getJson('/api/admin/account-report/vat-return-reconciliation');

    $response->assertSuccessful()
        ->assertJsonPath('data.computed_net_vat', 130)
        ->assertJsonPath('data.gl_net_vat', 0)
        ->assertJsonPath('data.delta', 130)
        ->assertJsonPath('data.reconciled', false);
});

it('reports account_configured=false when no VAT account is set', function () {
    AccountSetting::query()->delete();
    AccountSetting::create(['company_id' => $this->company->id]);

    makeTaxedInvoice($this, 'INV-VAT-3', 1000, 130);

    $response = $this->getJson('/api/admin/account-report/vat-return-reconciliation');

    $response->assertSuccessful()
        ->assertJsonPath('data.account_configured', false)
        ->assertJsonPath('data.computed_net_vat', 130)
        ->assertJsonPath('data.gl_net_vat', 0)
        ->assertJsonPath('data.reconciled', false);
});

it('excludes soft-deleted VAT journals from the GL balance', function () {
    makeTaxedInvoice($this, 'INV-VAT-4', 1000, 130);
    postVatJournal($this, cr: 130, dr: 0, voucher: 'JV-OUT-2');

    $voided = Journal::withoutGlobalScopes()->create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'type' => JournalTypeEnum::JOURNAL_VOUCHER,
        'voucher_no' => 'JV-VOID',
        'date' => '2026-03-12',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
    ]);
    JournalItem::create([
        'journal_id' => $voided->id, 'account_id' => $this->vatAccount->id,
        'dr_amount' => 0, 'cr_amount' => 999,
    ]);
    $voided->delete();

    $response = $this->getJson('/api/admin/account-report/vat-return-reconciliation');

    $response->assertSuccessful()
        ->assertJsonPath('data.gl_net_vat', 130)
        ->assertJsonPath('data.reconciled', true);
});
