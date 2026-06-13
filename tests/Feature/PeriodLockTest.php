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
use Laravel\Sanctum\Sanctum;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Services\TenantService;
use App\Models\AccountingPeriod;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\AccountingPeriodStatusEnum;
use App\Enums\InventoryCostingMethodEnum;
use App\Services\Accounting\PeriodLockGuard;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function periodWarmAllTablesCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

function makeUnpostedApprovedInvoice(object $test, string $no, string $date): Invoice
{
    $invoice = Invoice::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'invoice_no' => $no,
        'invoice_date' => $date,
        'party_id' => $test->customer->id,
        'create_user_id' => $test->user->id,
        'status' => StatusEnum::APPROVED,
        'approved_at' => now(),
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_variant_id' => $test->variant->id,
        'quantity' => 1,
        'rate' => 100,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'tax_line_type' => 'taxable',
    ]);

    return $invoice;
}

beforeEach(function () {
    periodWarmAllTablesCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Period Co', 'code' => 'PRD',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Closer',
        'email' => 'closer-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->customer = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Customer', 'code' => 'CUST-PRD',
        'type' => PartyTypeEnum::CUSTOMER,
    ]);

    $product = Product::create([
        'company_id' => $this->company->id, 'name' => 'Widget', 'code' => 'WIDGET-PRD',
    ]);
    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id, 'product_id' => $product->id,
        'sku' => 'SKU-PRD-1', 'sales_price' => 100, 'is_default' => true,
    ]);

    // Sales control accounts (so the GL-account guard doesn't fire first).
    $account = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'GL', 'code' => 'GL-PRD',
    ]);
    AccountSetting::create([
        'company_id' => $this->company->id,
        'customer_account_id' => $account->id,
        'sales_account_id' => $account->id,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

it('blocks GL posting when the document date falls in a closed period', function () {
    AccountingPeriod::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'period_number' => 1,
        'period_name' => 'Jan 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
        'status' => AccountingPeriodStatusEnum::CLOSED,
        'closed_by' => $this->user->id,
        'closed_at' => now(),
    ]);

    $invoice = makeUnpostedApprovedInvoice($this, 'INV-CLOSED', '2026-01-15');

    $this->postJson("/api/admin/account-report/invoice/{$invoice->id}/repost")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['period']);

    expect(Journal::withoutGlobalScopes()->count())->toBe(0);
});

it('allows GL posting when the document date falls in an open period', function () {
    AccountingPeriod::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'period_number' => 1,
        'period_name' => 'Jan 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
        'status' => AccountingPeriodStatusEnum::OPEN,
    ]);

    $invoice = makeUnpostedApprovedInvoice($this, 'INV-OPEN', '2026-01-15');

    $this->postJson("/api/admin/account-report/invoice/{$invoice->id}/repost")
        ->assertSuccessful();

    expect(Journal::withoutGlobalScopes()->count())->toBe(1);
});

it('allows GL posting when no period is defined (opt-in feature)', function () {
    // No AccountingPeriod rows — tenants that haven't enabled periods aren't blocked.
    $invoice = makeUnpostedApprovedInvoice($this, 'INV-NOPERIOD', '2026-01-15');

    $this->postJson("/api/admin/account-report/invoice/{$invoice->id}/repost")
        ->assertSuccessful();

    expect(Journal::withoutGlobalScopes()->count())->toBe(1);
});

it('also blocks postings into a locked period', function () {
    AccountingPeriod::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'period_number' => 1,
        'period_name' => 'Jan 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
        'status' => AccountingPeriodStatusEnum::LOCKED,
        'closed_by' => $this->user->id,
        'closed_at' => now(),
    ]);

    expect(fn () => app(PeriodLockGuard::class)->assertPostable(
        $this->company->id, $this->fiscalYear->id, '2026-01-15'
    ))->toThrow(\Illuminate\Validation\ValidationException::class);
});
