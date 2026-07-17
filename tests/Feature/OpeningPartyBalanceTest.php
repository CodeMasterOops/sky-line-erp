<?php

use App\Models\Bill;
use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\Receipt;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use App\Models\AccountGroup;
use Laravel\Sanctum\Sanctum;
use App\Enums\JournalTypeEnum;
use App\Models\AccountSetting;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;
use App\Enums\AccountGroupTypeEnum;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function openingWarmCache(): void
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
    openingWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => 'OB-FY',
        'year_code' => 'OBF',
        'start_date' => '2024-07-16',
        'end_date' => '2025-07-15',
    ]);

    $this->company = Company::create([
        'company_name' => 'Opening Balance Co',
        'code' => 'OBC',
        'fiscal_year_id' => $this->fiscalYear->id,
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'OB Tester',
        'email' => 'obtest-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $assetGroup = AccountGroup::create([
        'company_id' => $this->company->id,
        'name' => 'Assets', 'code' => 'ASS', 'account_type' => AccountGroupTypeEnum::Asset,
    ]);
    $liabilityGroup = AccountGroup::create([
        'company_id' => $this->company->id,
        'name' => 'Liabilities', 'code' => 'LIA', 'account_type' => AccountGroupTypeEnum::Liability,
    ]);
    $equityGroup = AccountGroup::create([
        'company_id' => $this->company->id,
        'name' => 'Equity', 'code' => 'EQU', 'account_type' => AccountGroupTypeEnum::Equity,
    ]);

    $this->arAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => $assetGroup->id,
        'name' => 'Sundry Debtors', 'code' => 'SD',
    ]);
    $this->apAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => $liabilityGroup->id,
        'name' => 'Sundry Creditors', 'code' => 'SC',
    ]);
    $this->obeAccount = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => $equityGroup->id,
        'name' => 'Opening Balance Equity', 'code' => 'OBE',
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'customer_account_id' => $this->arAccount->id,
        'supplier_account_id' => $this->apAccount->id,
        'opening_balance_equity_account_id' => $this->obeAccount->id,
    ]);

    $this->customer = Party::create([
        'company_id' => $this->company->id, 'type' => PartyTypeEnum::CUSTOMER,
        'name' => 'Acme Customer', 'code' => 'CUST1', 'is_active' => true,
    ]);
    $this->supplier = Party::create([
        'company_id' => $this->company->id, 'type' => PartyTypeEnum::SUPPLIER,
        'name' => 'Acme Supplier', 'code' => 'SUPP1', 'is_active' => true,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

it('posts a customer opening balance as an opening invoice with DR AR / CR Opening Balance Equity', function () {
    $response = $this->postJson('/api/admin/opening-balance/customers', [
        'date' => '2024-07-16',
        'lines' => [
            ['party_id' => $this->customer->id, 'amount' => 5000],
        ],
    ]);

    $response->assertCreated();

    $invoice = Invoice::where('party_id', $this->customer->id)->where('is_opening', true)->firstOrFail();
    expect((float) $invoice->opening_amount)->toBe(5000.0);
    expect($invoice->status)->toBe(StatusEnum::APPROVED);

    $journal = Journal::where('reference_type', $invoice->getMorphClass())
        ->where('reference_id', $invoice->id)
        ->where('type', JournalTypeEnum::OPENING_BALANCE->value)
        ->with('journalItems')
        ->firstOrFail();

    $dr = $journal->journalItems->firstWhere('account_id', $this->arAccount->id);
    $cr = $journal->journalItems->firstWhere('account_id', $this->obeAccount->id);

    expect((float) $dr->dr_amount)->toBe(5000.0);
    expect((float) $cr->cr_amount)->toBe(5000.0);
    expect(round($journal->journalItems->sum('dr_amount') - $journal->journalItems->sum('cr_amount'), 2))->toBe(0.0);
});

it('posts a supplier opening balance as an opening bill with DR Opening Balance Equity / CR AP', function () {
    $response = $this->postJson('/api/admin/opening-balance/suppliers', [
        'date' => '2024-07-16',
        'lines' => [
            ['party_id' => $this->supplier->id, 'amount' => 3000],
        ],
    ]);

    $response->assertCreated();

    $bill = Bill::where('party_id', $this->supplier->id)->where('is_opening', true)->firstOrFail();
    expect((float) $bill->opening_amount)->toBe(3000.0);

    $journal = Journal::where('reference_type', $bill->getMorphClass())
        ->where('reference_id', $bill->id)
        ->where('type', JournalTypeEnum::OPENING_BALANCE->value)
        ->with('journalItems')
        ->firstOrFail();

    expect((float) $journal->journalItems->firstWhere('account_id', $this->obeAccount->id)->dr_amount)->toBe(3000.0);
    expect((float) $journal->journalItems->firstWhere('account_id', $this->apAccount->id)->cr_amount)->toBe(3000.0);
});

it('reflects the customer opening balance in the statement opening balance', function () {
    $this->postJson('/api/admin/opening-balance/customers', [
        'date' => '2024-07-16',
        'lines' => [['party_id' => $this->customer->id, 'amount' => 5000]],
    ])->assertCreated();

    $response = $this->getJson('/api/admin/sales-report/sales-ledger?'.http_build_query([
        'party_id' => $this->customer->id,
        'from_date' => '2024-08-01',
        'to_date' => '2025-07-15',
    ]));

    $response->assertOk();
    expect((float) $response->json('data.opening_balance'))->toBe(5000.0);
});

it('includes the opening invoice in AR aging and drops it once settled', function () {
    $this->postJson('/api/admin/opening-balance/customers', [
        'date' => '2024-07-16',
        'lines' => [['party_id' => $this->customer->id, 'amount' => 5000]],
    ])->assertCreated();

    $invoice = Invoice::where('party_id', $this->customer->id)->where('is_opening', true)->firstOrFail();

    $aging = $this->getJson('/api/admin/account-report/ar-aging?start_date=2024-07-16&end_date=2025-07-15');
    $aging->assertOk();
    expect((float) $aging->json('data.buckets.total'))->toBe(5000.0);

    $receipt = Receipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->customer->id,
        'receipt_no' => 'RCPT-1',
        'receipt_date' => '2024-09-01',
        'payment_method' => 'Cash',
        'account_id' => $this->arAccount->id,
        'status' => StatusEnum::APPROVED->value,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);
    DB::table('receipt_allocations')->insert([
        'receipt_id' => $receipt->id,
        'invoice_id' => $invoice->id,
        'amount' => 5000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $agingAfter = $this->getJson('/api/admin/account-report/ar-aging?start_date=2024-07-16&end_date=2025-07-15');
    $agingAfter->assertOk();
    expect((float) $agingAfter->json('data.buckets.total'))->toBe(0.0);
});

it('rejects a duplicate opening balance for the same party', function () {
    $payload = [
        'date' => '2024-07-16',
        'lines' => [['party_id' => $this->customer->id, 'amount' => 5000]],
    ];

    $this->postJson('/api/admin/opening-balance/customers', $payload)->assertCreated();
    $this->postJson('/api/admin/opening-balance/customers', $payload)->assertStatus(422);

    expect(Invoice::where('party_id', $this->customer->id)->where('is_opening', true)->count())->toBe(1);
});

it('marks opening invoices as skipped so they never enter the IRD queue', function () {
    $this->postJson('/api/admin/opening-balance/customers', [
        'date' => '2024-07-16',
        'lines' => [['party_id' => $this->customer->id, 'amount' => 5000]],
    ])->assertCreated();

    $invoice = Invoice::where('party_id', $this->customer->id)->where('is_opening', true)->firstOrFail();
    expect($invoice->ird_sync_status)->toBe('skipped');
});
