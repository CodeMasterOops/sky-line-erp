<?php

use App\Models\User;
use App\Models\Party;
use App\Models\Branch;
use App\Models\Account;
use App\Models\Company;
use App\Models\Journal;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use App\Models\AccountGroup;
use Laravel\Sanctum\Sanctum;
use App\Enums\JournalTypeEnum;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Services\Accounting\CoaInsertService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function phase5WarmAllTablesCache(): void
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
    phase5WarmAllTablesCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'P5 Co', 'code' => 'P5C',
        'inventory_costing_method' => 'fifo',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Acct', 'email' => 'p5-'.uniqid().'@example.com',
        'password' => bcrypt('password'), 'user_type' => UserTypeEnum::ADMIN,
    ]);

    TenantService::setCompanyId($this->company->id);
    (new CoaInsertService($this->company))->saveCoaData();

    Sanctum::actingAs($this->user, ['*'], 'admin');
});

function p5Account(int $companyId, string $code): Account
{
    return Account::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->firstOrFail();
}

// ── CoA hygiene ──────────────────────────────────────────────────────────────

it('renames the Share Capital group to SHC to avoid the SC collision', function () {
    $shareCapital = AccountGroup::withoutGlobalScopes()
        ->where('company_id', $this->company->id)->where('name', 'Share Capital')->first();

    expect($shareCapital->code)->toBe('SHC');

    // Sundry Creditors keeps the SC account code; no group now collides with it.
    expect(AccountGroup::withoutGlobalScopes()->where('company_id', $this->company->id)->where('code', 'SC')->exists())
        ->toBeFalse()
        ->and(Account::withoutGlobalScopes()->where('company_id', $this->company->id)->where('code', 'SC')->exists())
        ->toBeTrue();
});

it('ships neutral default ledger names and drops the sample loan', function () {
    expect(p5Account($this->company->id, 'AB')->name)->toBe('Bank Account 1')
        ->and(Account::withoutGlobalScopes()->where('company_id', $this->company->id)->where('code', 'ATL0')->exists())
        ->toBeFalse();
});

it('ships a dedicated rounding ledger in the default CoA', function () {
    expect(Account::withoutGlobalScopes()->where('company_id', $this->company->id)->where('code', 'ROUND')->exists())
        ->toBeTrue();
});

// ── VAT register cache (auto-invalidating fingerprint) ───────────────────────

it('reflects a newly added bill in the purchase register despite caching', function () {
    $supplier = Party::create([
        'company_id' => $this->company->id, 'type' => PartyTypeEnum::SUPPLIER->value,
        'name' => 'Sup', 'code' => 'SUPX', 'pan' => '123456789',
    ]);

    $makeBill = fn (string $no, string $date) => \App\Models\Bill::create([
        'company_id' => $this->company->id, 'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $supplier->id, 'supplier_pan' => '123456789',
        'bill_no' => $no, 'supplier_invoice_no' => 'IRD-'.$no, 'bill_date' => $date,
        'status' => StatusEnum::APPROVED->value,
        'create_user_id' => $this->user->id, 'approve_user_id' => $this->user->id, 'approved_at' => now(),
    ]);

    $url = '/api/admin/account-report/vat-purchase-register?start_date=2026-01-01&end_date=2026-12-31';

    $makeBill('B1', '2026-02-01');
    expect($this->getJson($url)->assertSuccessful()->json('data.rows'))->toHaveCount(1);

    // Second identical request hits the cache and returns the same shape.
    expect($this->getJson($url)->assertSuccessful()->json('data.rows'))->toHaveCount(1);

    // Adding a bill changes the fingerprint, busting the cache.
    $makeBill('B2', '2026-02-02');
    expect($this->getJson($url)->assertSuccessful()->json('data.rows'))->toHaveCount(2);
});

// ── Optional branch filter on financial statements ───────────────────────────

it('filters profit & loss by branch', function () {
    $branchA = Branch::create(['company_id' => $this->company->id, 'name' => 'A', 'code' => 'A']);
    $branchB = Branch::create(['company_id' => $this->company->id, 'name' => 'B', 'code' => 'B']);

    $bank = p5Account($this->company->id, 'AB');
    $sales = p5Account($this->company->id, 'SOG');

    $post = function (?int $branchId, float $amount) use ($bank, $sales) {
        $journal = Journal::create([
            'company_id' => $this->company->id, 'branch_id' => $branchId,
            'fiscal_year_id' => $this->fiscalYear->id, 'type' => JournalTypeEnum::RECEIPT->value,
            'voucher_no' => 'RC-'.uniqid(), 'date' => '2026-03-01', 'status' => StatusEnum::APPROVED->value,
            'create_user_id' => $this->user->id, 'approve_user_id' => $this->user->id, 'approved_at' => now(),
        ]);
        $journal->journalItems()->create(['account_id' => $bank->id, 'dr_amount' => $amount, 'cr_amount' => 0]);
        $journal->journalItems()->create(['account_id' => $sales->id, 'dr_amount' => 0, 'cr_amount' => $amount]);
    };

    $post($branchA->id, 1000);
    $post($branchB->id, 500);

    $base = '/api/admin/account-report/profit-loss?start_date=2026-01-01&end_date=2026-12-31';

    // Income is credit-normal in the P&L summary (stored dr − cr ⇒ negative).
    $income = fn (string $url) => abs((float) $this->getJson($url)->assertSuccessful()->json('data.summary.income'));

    expect($income($base))->toBe(1500.0)
        ->and($income($base."&branch_id={$branchA->id}"))->toBe(1000.0)
        ->and($income($base."&branch_id={$branchB->id}"))->toBe(500.0);
});
