<?php

use App\Models\Bill;
use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Journal;
use App\Models\Product;
use App\Models\BillItem;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function billRepostWarmAllTablesCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

function makeApprovedBill(object $test, string $no, float $rate, int $qty): Bill
{
    $bill = Bill::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'party_id' => $test->supplier->id,
        'bill_no' => $no,
        'bill_date' => now()->toDateString(),
        'create_user_id' => $test->user->id,
        'status' => StatusEnum::APPROVED,
        'approved_at' => now(),
    ]);

    BillItem::create([
        'bill_id' => $bill->id,
        'product_variant_id' => $test->variant->id,
        'quantity' => $qty,
        'rate' => $rate,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'tax_line_type' => 'taxable',
    ]);

    return $bill;
}

function configurePurchaseAccounts(object $test): void
{
    $account = Account::create([
        'company_id' => $test->company->id,
        'account_group_id' => null,
        'name' => 'Control',
        'code' => 'CTRL-BILL',
    ]);

    AccountSetting::create([
        'company_id' => $test->company->id,
        'purchase_account_id' => $account->id,
        'supplier_account_id' => $account->id,
    ]);
}

beforeEach(function () {
    billRepostWarmAllTablesCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Bill Co',
        'code' => 'BLC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Biller',
        'email' => 'biller-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->supplier = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Supplier',
        'code' => 'SUP-BILL',
        'type' => PartyTypeEnum::SUPPLIER,
    ]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Widget',
        'code' => 'WIDGET-BILL',
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'sku' => 'SKU-BILL-1',
        'purchase_price' => 50,
        'is_default' => true,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

it('lists unposted approved bills and re-posts them', function () {
    configurePurchaseAccounts($this);
    $bill = makeApprovedBill($this, 'BILL-REPOST', 50, 2);

    expect($this->getJson('/api/admin/account-report/unposted-documents')
        ->json('data.summary.purchase_count'))->toBe(1);

    $this->postJson("/api/admin/account-report/bill/{$bill->id}/repost")
        ->assertSuccessful()
        ->assertJsonPath('message', 'Bill posted to the ledger successfully.');

    expect(Journal::withoutGlobalScopes()
        ->where('reference_type', (new Bill)->getMorphClass())
        ->where('reference_id', $bill->id)
        ->count())->toBe(1);

    expect($this->getJson('/api/admin/account-report/unposted-documents')
        ->json('data.summary.purchase_count'))->toBe(0);
});

it('bill re-post is idempotent', function () {
    configurePurchaseAccounts($this);
    $bill = makeApprovedBill($this, 'BILL-REPOST-2', 50, 1);

    $this->postJson("/api/admin/account-report/bill/{$bill->id}/repost")->assertSuccessful();

    $this->postJson("/api/admin/account-report/bill/{$bill->id}/repost")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Bill is already posted to the ledger.');

    expect(Journal::withoutGlobalScopes()
        ->where('reference_id', $bill->id)
        ->where('reference_type', (new Bill)->getMorphClass())
        ->count())->toBe(1);
});

it('refuses to re-post a bill when purchase accounts are not configured', function () {
    $bill = makeApprovedBill($this, 'BILL-REPOST-3', 50, 1);

    $this->postJson("/api/admin/account-report/bill/{$bill->id}/repost")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['account_setting']);

    expect(Journal::withoutGlobalScopes()->count())->toBe(0);
});

it('blocks bill approval when purchase accounts are not configured', function () {
    $bill = Bill::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->supplier->id,
        'bill_no' => 'BILL-DRAFT',
        'bill_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    BillItem::create([
        'bill_id' => $bill->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
        'rate' => 50,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'tax_line_type' => 'taxable',
    ]);

    $this->postJson("/api/admin/bill/{$bill->id}/approve")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['account_setting']);

    expect(Bill::find($bill->id)->status)->not->toBe(StatusEnum::APPROVED);
    expect(Journal::withoutGlobalScopes()->count())->toBe(0);
});
