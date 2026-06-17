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
use App\Models\JournalItem;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Enums\JournalTypeEnum;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function booksHealthWarmAllTablesCache(): void
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
    booksHealthWarmAllTablesCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Health Co', 'code' => 'HLTH',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Health Admin',
        'email' => 'hlth-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

it('reports healthy when there are no journals or documents', function () {
    $response = $this->getJson('/api/admin/account-report/books-health');

    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'healthy')
        ->assertJsonPath('data.healthy', true)
        ->assertJsonPath('data.issues', []);
});

it('reports critical when an unbalanced journal exists', function () {
    $account = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'Misc', 'code' => 'MISC-HLTH',
    ]);
    $journal = Journal::withoutGlobalScopes()->create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'type' => JournalTypeEnum::JOURNAL_VOUCHER,
        'voucher_no' => 'JV-HLTH-BAD',
        'date' => '2026-03-01',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
    ]);
    JournalItem::create([
        'journal_id' => $journal->id, 'account_id' => $account->id,
        'dr_amount' => 100, 'cr_amount' => 0,
    ]);

    $response = $this->getJson('/api/admin/account-report/books-health');

    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'critical')
        ->assertJsonPath('data.healthy', false);

    $checks = collect($response->json('data.checks'));
    expect($checks->firstWhere('key', 'unbalanced_journals'))
        ->toMatchArray(['ok' => false, 'severity' => 'critical']);
});

it('reports warning when an approved invoice has no GL journal', function () {
    $customer = Party::create([
        'company_id' => $this->company->id, 'name' => 'Customer', 'code' => 'C-HLTH',
        'type' => PartyTypeEnum::CUSTOMER,
    ]);
    $product = Product::create([
        'company_id' => $this->company->id, 'name' => 'Widget', 'code' => 'W-HLTH',
    ]);
    $variant = ProductVariant::create([
        'company_id' => $this->company->id, 'product_id' => $product->id,
        'sku' => 'SKU-HLTH', 'sales_price' => 100, 'is_default' => true,
    ]);

    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'invoice_no' => 'INV-HLTH-1',
        'invoice_date' => '2026-03-10',
        'party_id' => $customer->id,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
        'approved_at' => now(),
    ]);
    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_variant_id' => $variant->id,
        'quantity' => 1, 'rate' => 100, 'discount_amount' => 0, 'tax_amount' => 0,
        'tax_line_type' => 'taxable',
    ]);

    $response = $this->getJson('/api/admin/account-report/books-health');

    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'warning')
        ->assertJsonPath('data.healthy', false);

    $checks = collect($response->json('data.checks'));
    expect($checks->firstWhere('key', 'unposted_documents'))
        ->toMatchArray(['ok' => false, 'severity' => 'warning']);
    expect($checks->firstWhere('key', 'unbalanced_journals')['ok'])->toBeTrue();
});
