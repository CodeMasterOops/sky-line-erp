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
use App\Models\AccountingPeriod;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\AccountingPeriodStatusEnum;
use App\Enums\InventoryCostingMethodEnum;
use App\Services\Accounting\AccountingPeriodGenerator;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function yearEndWarmAllTablesCache(): void
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
    yearEndWarmAllTablesCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Year End Co', 'code' => 'YE',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Closer',
        'email' => 'ye-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

function generatePeriods(object $test): int
{
    AccountingPeriodGenerator::generateForCompanyIfMissing($test->company->id, $test->fiscalYear);

    return AccountingPeriod::withoutGlobalScopes()
        ->where('company_id', $test->company->id)
        ->where('fiscal_year_id', $test->fiscalYear->id)
        ->count();
}

it('reports the fiscal year as ready to close when the books are clean', function () {
    generatePeriods($this);

    $response = $this->getJson('/api/admin/accounting-period/close-year/readiness?fiscal_year_id='.$this->fiscalYear->id);

    $response->assertSuccessful()
        ->assertJsonPath('data.ready', true)
        ->assertJsonPath('data.checks.periods_generated', true)
        ->assertJsonPath('data.checks.no_unbalanced_journals', true)
        ->assertJsonPath('data.checks.no_unposted_documents', true)
        ->assertJsonPath('data.unbalanced_journals_count', 0)
        ->assertJsonPath('data.unposted_documents_count', 0);
});

it('closes every open period of the fiscal year when ready', function () {
    $count = generatePeriods($this);

    $response = $this->postJson('/api/admin/accounting-period/close-year', [
        'fiscal_year_id' => $this->fiscalYear->id,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.periods_closed', $count);

    $stillOpen = AccountingPeriod::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('fiscal_year_id', $this->fiscalYear->id)
        ->where('status', AccountingPeriodStatusEnum::OPEN->value)
        ->count();

    expect($stillOpen)->toBe(0);
});

it('blocks the close when an unbalanced journal exists', function () {
    generatePeriods($this);

    $account = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'Misc', 'code' => 'MISC-YE',
    ]);

    // DR 100, CR 0 — deliberately unbalanced.
    $journal = Journal::withoutGlobalScopes()->create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'type' => JournalTypeEnum::JOURNAL_VOUCHER,
        'voucher_no' => 'JV-YE-BAD',
        'date' => '2026-03-01',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
    ]);
    JournalItem::create([
        'journal_id' => $journal->id, 'account_id' => $account->id,
        'dr_amount' => 100, 'cr_amount' => 0,
    ]);

    $readiness = $this->getJson('/api/admin/accounting-period/close-year/readiness?fiscal_year_id='.$this->fiscalYear->id);
    $readiness->assertSuccessful()
        ->assertJsonPath('data.ready', false)
        ->assertJsonPath('data.checks.no_unbalanced_journals', false);

    $response = $this->postJson('/api/admin/accounting-period/close-year', [
        'fiscal_year_id' => $this->fiscalYear->id,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('year_end');

    // Periods must be untouched.
    $open = AccountingPeriod::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('fiscal_year_id', $this->fiscalYear->id)
        ->where('status', AccountingPeriodStatusEnum::OPEN->value)
        ->count();
    expect($open)->toBeGreaterThan(0);
});

it('blocks the close when no accounting periods have been generated', function () {
    $response = $this->postJson('/api/admin/accounting-period/close-year', [
        'fiscal_year_id' => $this->fiscalYear->id,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('fiscal_year_id');
});
