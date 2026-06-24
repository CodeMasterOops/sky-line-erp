<?php

use App\Models\Role;
use App\Models\User;
use App\Models\Branch;
use App\Models\Company;
use App\Models\BranchUser;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Services\Accounting\CoaInsertService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function finReportWarmCache(): void
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
    finReportWarmCache();

    $fy = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
    ]);
    $this->company = Company::create([
        'fiscal_year_id' => $fy->id, 'company_name' => 'Co', 'code' => 'FR-CO',
        'inventory_costing_method' => 'fifo',
    ]);

    TenantService::setCompanyId($this->company->id);
    (new CoaInsertService($this->company))->saveCoaData();

    $this->branchA = Branch::create(['company_id' => $this->company->id, 'name' => 'A', 'code' => 'FR-A', 'is_head_office' => true, 'is_active' => true]);
    $this->branchB = Branch::create(['company_id' => $this->company->id, 'name' => 'B', 'code' => 'FR-B', 'is_active' => true]);

    // A non-admin who may view P&L and is assigned to branch A only.
    $this->user = User::create([
        'company_id' => $this->company->id, 'name' => 'Branch Acct', 'email' => 'fr-'.uniqid().'@t.test',
        'password' => bcrypt('x'), 'user_type' => UserTypeEnum::USER,
    ]);
    $role = Role::create(['company_id' => $this->company->id, 'name' => 'Branch Accountant', 'permissions' => ['profit_loss']]);
    $this->user->roles()->attach($role);
    BranchUser::create([
        'company_id' => $this->company->id, 'branch_id' => $this->branchA->id,
        'user_id' => $this->user->id, 'role_id' => $role->id, 'is_active' => true,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
});

afterEach(fn () => TenantService::reset());

it('lets a branch user view the P&L for their own branch', function () {
    $this->getJson('/api/admin/account-report/profit-loss?branch_id='.$this->branchA->id)
        ->assertSuccessful();
});

it('forbids a branch user from viewing another branch P&L via the branch_id param', function () {
    $this->getJson('/api/admin/account-report/profit-loss?branch_id='.$this->branchB->id)
        ->assertForbidden();
});

it('forbids a branch user from the balance sheet of another branch', function () {
    // balance_sheet permission is separate; grant it to isolate the branch check.
    $this->user->roles()->first()->update(['permissions' => ['profit_loss', 'balance_sheet']]);

    $this->getJson('/api/admin/account-report/balance-sheet?branch_id='.$this->branchB->id)
        ->assertForbidden();
});

it('branch-scopes the VAT sales and purchase registers (sales/purchase) by access', function () {
    $this->user->roles()->first()->update(['permissions' => ['profit_loss', 'vat_report']]);

    // Own branch is allowed; another branch is forbidden for both registers.
    $this->getJson('/api/admin/account-report/vat-sales-register?branch_id='.$this->branchA->id)->assertSuccessful();
    $this->getJson('/api/admin/account-report/vat-sales-register?branch_id='.$this->branchB->id)->assertForbidden();

    $this->getJson('/api/admin/account-report/vat-purchase-register?branch_id='.$this->branchA->id)->assertSuccessful();
    $this->getJson('/api/admin/account-report/vat-purchase-register?branch_id='.$this->branchB->id)->assertForbidden();
});
