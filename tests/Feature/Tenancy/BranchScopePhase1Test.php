<?php

use App\Models\Branch;
use App\Models\Budget;
use App\Tenancy\TRule;
use App\Models\Account;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\BankAccount;
use App\Models\AccountGroup;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function phase1WarmAllTablesCache(): void
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
    phase1WarmAllTablesCache();

    $this->fy = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fy->id, 'company_name' => 'Co', 'code' => 'P1-CO',
        'inventory_costing_method' => 'fifo',
    ]);

    $this->branchA = Branch::create([
        'company_id' => $this->company->id, 'name' => 'Branch A', 'code' => 'BR-A',
        'is_head_office' => true, 'is_active' => true,
    ]);
    $this->branchB = Branch::create([
        'company_id' => $this->company->id, 'name' => 'Branch B', 'code' => 'BR-B',
        'is_head_office' => false, 'is_active' => true,
    ]);

    TenantService::setCompanyId($this->company->id);
});

afterEach(function () {
    TenantService::reset();
});

it('auto-stamps the active branch_id when creating a newly-scoped model', function () {
    TenantService::setBranchId($this->branchA->id);

    $budget = Budget::create([
        'fiscal_year_id' => $this->fy->id,
        'name' => 'Q1 Budget',
        'is_active' => true,
    ]);

    expect($budget->branch_id)->toBe($this->branchA->id);
});

it('hides other branches rows when a branch is active', function () {
    Budget::create(['fiscal_year_id' => $this->fy->id, 'name' => 'A budget', 'branch_id' => $this->branchA->id]);
    Budget::create(['fiscal_year_id' => $this->fy->id, 'name' => 'B budget', 'branch_id' => $this->branchB->id]);

    TenantService::setBranchId($this->branchA->id);

    expect(Budget::count())->toBe(1);
    expect(Budget::first()->branch_id)->toBe($this->branchA->id);

    TenantService::setBranchId($this->branchB->id);

    expect(Budget::count())->toBe(1);
    expect(Budget::first()->branch_id)->toBe($this->branchB->id);
});

it('keeps null-branch (company-wide) budgets out of a branch view but visible with no branch active', function () {
    Budget::create(['fiscal_year_id' => $this->fy->id, 'name' => 'Company-wide', 'branch_id' => null]);

    TenantService::setBranchId($this->branchA->id);
    expect(Budget::count())->toBe(0);

    TenantService::setBranchId(null);
    expect(Budget::count())->toBe(1);
});

it('branch-scopes existence validation so a user cannot reference another branch row', function () {
    $group = AccountGroup::create(['company_id' => $this->company->id, 'name' => 'Assets', 'code' => 'AST']);
    $account = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => $group->id,
        'name' => 'Cash', 'code' => 'CASH', 'is_active' => true,
    ]);

    $bankA = BankAccount::create([
        'company_id' => $this->company->id, 'branch_id' => $this->branchA->id,
        'account_id' => $account->id, 'bank_name' => 'Bank A', 'account_number' => 'A-1',
        'currency' => 'NPR', 'is_active' => true,
    ]);
    $bankB = BankAccount::create([
        'company_id' => $this->company->id, 'branch_id' => $this->branchB->id,
        'account_id' => $account->id, 'bank_name' => 'Bank B', 'account_number' => 'B-1',
        'currency' => 'NPR', 'is_active' => true,
    ]);

    TenantService::setBranchId($this->branchA->id);

    $rule = ['x' => [TRule::exists('bank_accounts', 'id')->withoutTrashed()]];

    expect(Validator::make(['x' => $bankA->id], $rule)->fails())->toBeFalse();
    expect(Validator::make(['x' => $bankB->id], $rule)->fails())->toBeTrue();
});

it('does not branch-scope uniqueness checks (codes remain unique per company)', function () {
    // TenantUnique must NOT gain a branch predicate: a code used in branch A must
    // still collide in branch B (per-company uniqueness, frozen decision).
    $ruleString = (string) TRule::unique('parties', 'code')->withoutTrashed();

    TenantService::setBranchId($this->branchA->id);

    expect($ruleString)->not->toContain('branch_id');
});
