<?php

use App\Models\Branch;
use App\Models\Account;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use App\Http\Validation\BranchScopedExists;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function branchScopedExistsWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
}

function makeJournalItem(int $companyId, ?int $branchId, int $accountId, int $fyId, int $userId, string $voucher): int
{
    $journalId = DB::table('journals')->insertGetId([
        'company_id' => $companyId,
        'branch_id' => $branchId,
        'fiscal_year_id' => $fyId,
        'type' => 'journal_voucher',
        'voucher_no' => $voucher,
        'date' => '2026-06-01',
        'create_user_id' => $userId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return DB::table('journal_items')->insertGetId([
        'journal_id' => $journalId,
        'account_id' => $accountId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

beforeEach(function () {
    branchScopedExistsWarmCache();
    TenantService::reset();

    $fy = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);
    $this->company = Company::create([
        'fiscal_year_id' => $fy->id, 'company_name' => 'Co', 'code' => 'BSE-CO',
        'inventory_costing_method' => 'fifo',
    ]);
    $this->branchA = Branch::create(['company_id' => $this->company->id, 'name' => 'A', 'code' => 'BSE-A', 'is_head_office' => true, 'is_active' => true]);
    $this->branchB = Branch::create(['company_id' => $this->company->id, 'name' => 'B', 'code' => 'BSE-B', 'is_active' => true]);

    TenantService::setCompanyId($this->company->id);
    $account = Account::create(['company_id' => $this->company->id, 'name' => 'Cash', 'code' => 'BSE-CASH']);
    $userId = DB::table('users')->insertGetId([
        'company_id' => $this->company->id, 'name' => 'U', 'email' => 'bse-'.uniqid().'@t.test',
        'password' => bcrypt('x'), 'user_type' => 'admin', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->itemA = makeJournalItem($this->company->id, $this->branchA->id, $account->id, $fy->id, $userId, 'JV-A');
    $this->itemB = makeJournalItem($this->company->id, $this->branchB->id, $account->id, $fy->id, $userId, 'JV-B');
});

afterEach(fn () => TenantService::reset());

function bseRule(): array
{
    return ['x' => [BranchScopedExists::child('journal_items', 'journal_id', 'journals')]];
}

it('accepts a child whose parent is in the active branch', function () {
    TenantService::setBranchId($this->branchA->id);

    expect(Validator::make(['x' => $this->itemA], bseRule())->fails())->toBeFalse();
});

it('rejects a child whose parent belongs to another branch', function () {
    TenantService::setBranchId($this->branchA->id);

    expect(Validator::make(['x' => $this->itemB], bseRule())->fails())->toBeTrue();
});

it('falls back to company scope when no branch is active', function () {
    TenantService::setBranchId(null);

    // Admin with no branch selected: both same-company children are valid.
    expect(Validator::make(['x' => $this->itemA], bseRule())->fails())->toBeFalse()
        ->and(Validator::make(['x' => $this->itemB], bseRule())->fails())->toBeFalse();
});
