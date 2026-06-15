<?php

use App\Models\User;
use App\Models\Account;
use App\Models\Company;
use App\Models\Journal;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\JournalItem;
use Laravel\Sanctum\Sanctum;
use App\Enums\JournalTypeEnum;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use App\Services\Accounting\JournalBalanceGuard;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function balanceWarmAllTablesCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

function makeJournalWithItems(object $test, string $voucher, float $dr, float $cr): Journal
{
    $journal = Journal::withoutGlobalScopes()->create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'type' => JournalTypeEnum::INVOICE,
        'voucher_no' => $voucher,
        'date' => now()->toDateString(),
        'create_user_id' => $test->user->id,
        'status' => StatusEnum::APPROVED,
    ]);

    JournalItem::create([
        'journal_id' => $journal->id,
        'account_id' => $test->account->id,
        'dr_amount' => $dr, 'cr_amount' => 0,
    ]);
    JournalItem::create([
        'journal_id' => $journal->id,
        'account_id' => $test->account->id,
        'dr_amount' => 0, 'cr_amount' => $cr,
    ]);

    return $journal;
}

beforeEach(function () {
    balanceWarmAllTablesCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Balance Co', 'code' => 'BAL',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Auditor',
        'email' => 'auditor-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->account = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'GL', 'code' => 'GL-BAL',
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

it('throws when a journal is not balanced', function () {
    $journal = makeJournalWithItems($this, 'JV-IMBAL', 100, 90); // 10 delta

    app(JournalBalanceGuard::class)->assertBalanced($journal);
})->throws(RuntimeException::class, 'not balanced');

it('passes for a balanced journal within tolerance', function () {
    $journal = makeJournalWithItems($this, 'JV-BAL', 100.00, 100.005); // 0.005 delta

    app(JournalBalanceGuard::class)->assertBalanced($journal); // no throw
    expect(true)->toBeTrue();
});

it('lists unbalanced journals and excludes balanced ones from the report', function () {
    makeJournalWithItems($this, 'JV-BAL-1', 100, 100); // balanced — excluded
    makeJournalWithItems($this, 'JV-OFF-1', 150, 100); // delta +50 — listed
    makeJournalWithItems($this, 'JV-OFF-2', 80, 100);  // delta -20 — listed

    $response = $this->getJson('/api/admin/account-report/unbalanced-journals');

    $response->assertSuccessful()
        ->assertJsonPath('data.summary.count', 2);

    expect((float) $response->json('data.summary.total_delta'))->toBe(70.0);

    $vouchers = collect($response->json('data.rows'))->pluck('voucher_no')->sort()->values()->all();
    expect($vouchers)->toBe(['JV-OFF-1', 'JV-OFF-2']);
});

it('excludes soft-deleted journals from the unbalanced report', function () {
    $journal = makeJournalWithItems($this, 'JV-VOIDED', 150, 100); // imbalanced
    $journal->delete(); // soft-delete (simulating a void)

    $response = $this->getJson('/api/admin/account-report/unbalanced-journals');

    $response->assertSuccessful()
        ->assertJsonPath('data.summary.count', 0);
});
