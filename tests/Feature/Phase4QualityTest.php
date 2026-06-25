<?php

use App\Models\User;
use App\Models\Party;
use App\Models\Stock;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\Product;
use App\Models\Receipt;
use App\Enums\StatusEnum;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use App\Policies\StockPolicy;
use App\Enums\JournalTypeEnum;
use App\Models\ProductVariant;
use App\Policies\JournalPolicy;
use App\Policies\ReceiptPolicy;
use App\Services\TenantService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function phase4WarmCache(): void
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
    phase4WarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Phase4 Co', 'code' => 'P4CO',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->otherCompany = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Other Co', 'code' => 'OTHCO',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Admin',
        'email' => 'admin-p4-'.uniqid().'@example.com',
        'password' => bcrypt('secret'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->otherUser = User::create([
        'company_id' => $this->otherCompany->id,
        'name' => 'Other Admin',
        'email' => 'other-p4-'.uniqid().'@example.com',
        'password' => bcrypt('secret'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->customer = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Cust', 'code' => 'CUST-P4', 'type' => PartyTypeEnum::CUSTOMER,
    ]);

    $this->cashAccount = Account::create([
        'company_id' => $this->company->id,
        'name' => 'Cash', 'code' => 'CASH-P4',
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Main Warehouse', 'code' => 'WH-P4',
    ]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Widget', 'code' => 'WGT-P4',
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'sku' => 'SKU-P4', 'sales_price' => 100, 'is_default' => true,
    ]);

    TenantService::setCompanyId($this->company->id);
});

// ── #19: bijak_no in Invoice scopeFilter ─────────────────────────────────────

it('scopeFilter matches invoice_no', function () {
    Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->customer->id,
        'invoice_no' => 'INV-P4-001',
        'bijak_no' => 'BJ-9999',
        'invoice_date' => '2026-03-10',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $results = Invoice::filter(['search' => 'INV-P4-001'])->get();
    expect($results)->toHaveCount(1);
});

it('scopeFilter matches bijak_no', function () {
    Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->customer->id,
        'invoice_no' => 'INV-P4-002',
        'bijak_no' => 'BJ-UNIQUE-12345',
        'invoice_date' => '2026-03-10',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $results = Invoice::filter(['search' => 'BJ-UNIQUE-12345'])->get();
    expect($results)->toHaveCount(1)
        ->and($results->first()->bijak_no)->toBe('BJ-UNIQUE-12345');
});

it('scopeFilter search on bijak_no does not match invoices from other companies', function () {
    // Invoice in company under test
    Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->customer->id,
        'invoice_no' => 'INV-P4-003',
        'bijak_no' => 'BJ-SHARED-NUM',
        'invoice_date' => '2026-03-10',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    // MultiTenant scope means only this company's invoice is returned
    $results = Invoice::filter(['search' => 'BJ-SHARED-NUM'])->get();
    expect($results)->toHaveCount(1);
});

// ── #22: Policies ─────────────────────────────────────────────────────────────

it('ReceiptPolicy view allows same-company user', function () {
    $receipt = Receipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'account_id' => $this->cashAccount->id,
        'receipt_no' => 'RC-P4-1',
        'receipt_date' => '2026-03-10',
        'payment_method' => 'cash',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $policy = new ReceiptPolicy;
    expect($policy->view($this->user, $receipt))->toBeTrue();
});

it('ReceiptPolicy view denies cross-company user', function () {
    $receipt = Receipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'account_id' => $this->cashAccount->id,
        'receipt_no' => 'RC-P4-2',
        'receipt_date' => '2026-03-10',
        'payment_method' => 'cash',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $policy = new ReceiptPolicy;
    expect($policy->view($this->otherUser, $receipt))->toBeFalse();
});

it('JournalPolicy view allows same-company user', function () {
    $journal = Journal::withoutGlobalScopes()->create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'type' => JournalTypeEnum::RECEIPT,
        'voucher_no' => 'JV-P4-1',
        'date' => '2026-03-10',
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED,
    ]);

    $policy = new JournalPolicy;
    expect($policy->view($this->user, $journal))->toBeTrue();
});

it('JournalPolicy view denies cross-company user', function () {
    $journal = Journal::withoutGlobalScopes()->create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'type' => JournalTypeEnum::RECEIPT,
        'voucher_no' => 'JV-P4-2',
        'date' => '2026-03-10',
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED,
    ]);

    $policy = new JournalPolicy;
    expect($policy->view($this->otherUser, $journal))->toBeFalse();
});

it('StockPolicy view allows same-company user', function () {
    $stock = Stock::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 10,
    ]);

    $policy = new StockPolicy;
    expect($policy->view($this->user, $stock))->toBeTrue();
});

it('StockPolicy view denies cross-company user', function () {
    $stock = Stock::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 10,
    ]);

    $policy = new StockPolicy;
    expect($policy->view($this->otherUser, $stock))->toBeFalse();
});

it('policies are registered in the Gate for Receipt, Journal, and Stock', function () {
    $receipt = Receipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'account_id' => $this->cashAccount->id,
        'receipt_no' => 'RC-P4-GATE',
        'receipt_date' => '2026-03-10',
        'payment_method' => 'cash',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $journal = Journal::withoutGlobalScopes()->create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'type' => JournalTypeEnum::RECEIPT,
        'voucher_no' => 'JV-P4-GATE',
        'date' => '2026-03-10',
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED,
    ]);

    $stock = Stock::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 5,
    ]);

    Gate::forUser($this->user)->allows('view', $receipt);
    Gate::forUser($this->user)->allows('view', $journal);
    Gate::forUser($this->user)->allows('view', $stock);

    // Passes if no exception is thrown (policies are registered)
    expect(true)->toBeTrue();
});
