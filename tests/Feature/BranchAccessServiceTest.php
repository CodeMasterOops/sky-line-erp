<?php

use App\Models\Role;
use App\Models\User;
use App\Models\Branch;
use App\Models\Company;
use App\Models\BranchUser;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Services\TenantService;
use App\Services\BranchAccessService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Auth\Access\AuthorizationException;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function branchAccessWarmAllTablesCache(): void
{
    // Bust the stale cache so the lazy allTables() repopulates it with the
    // current schema on next access. We must NOT manually repopulate here:
    // Schema::getColumnListing('main.<table>') in SQLite returns empty arrays
    // for tables that have never been touched, which would prevent MultiTenant
    // from registering its creating callback on models that boot after this runs.
    Cache::forget(allTablesCacheKey());
}

beforeEach(function () {
    branchAccessWarmAllTablesCache();

    $fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $fiscalYear->id,
        'company_name' => 'Branch Access Co',
        'code' => 'BAC',
    ]);

    TenantService::setCompanyId($this->company->id);

    $this->branchA = Branch::create([
        'company_id' => $this->company->id,
        'name' => 'Branch A',
        'code' => 'BA',
        'is_head_office' => true,
        'is_active' => true,
    ]);

    $this->branchB = Branch::create([
        'company_id' => $this->company->id,
        'name' => 'Branch B',
        'code' => 'BB',
        'is_head_office' => false,
        'is_active' => true,
    ]);

    $this->adminUser = User::create([
        'company_id' => $this->company->id,
        'name' => 'Admin User',
        'email' => 'admin-ba-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->regularUser = User::create([
        'company_id' => $this->company->id,
        'name' => 'Regular User',
        'email' => 'user-ba-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::USER,
    ]);

    $this->role = Role::create([
        'company_id' => $this->company->id,
        'name' => 'Cashier',
        'permissions' => ['sales.view', 'sales.create'],
    ]);

    $this->service = new BranchAccessService;
});

afterEach(function () {
    // Reset branch-specific context; company_id is intentionally left set
    // because the static property persists across tests in the same process and
    // other test classes rely on the middleware (SetTenantContext) to re-establish
    // it from the authenticated user — resetting company_id here causes ordering-
    // dependent failures in those tests when this class runs first.
    TenantService::setBranchId(null);
    TenantService::setBranchRole(null);
    TenantService::setBranchPermissions(null);
});

// --- canUserAccessBranch ---

it('grants admin access to any branch', function () {
    expect($this->service->canUserAccessBranch($this->adminUser, $this->branchA->id))->toBeTrue();
    expect($this->service->canUserAccessBranch($this->adminUser, $this->branchB->id))->toBeTrue();
});

it('grants regular user access to an assigned branch', function () {
    BranchUser::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->regularUser->id,
        'is_active' => true,
    ]);

    expect($this->service->canUserAccessBranch($this->regularUser, $this->branchA->id))->toBeTrue();
});

it('denies regular user access to an unassigned branch', function () {
    expect($this->service->canUserAccessBranch($this->regularUser, $this->branchB->id))->toBeFalse();
});

it('denies access for an inactive branch assignment', function () {
    BranchUser::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->regularUser->id,
        'is_active' => false,
    ]);

    expect($this->service->canUserAccessBranch($this->regularUser, $this->branchA->id))->toBeFalse();
});

// --- getAccessibleBranches ---

it('returns all active company branches for admin', function () {
    $branches = $this->service->getAccessibleBranches($this->adminUser);

    expect($branches)->toHaveCount(2)
        ->and($branches->pluck('id')->toArray())->toContain($this->branchA->id, $this->branchB->id);
});

it('returns only assigned branches for regular user', function () {
    BranchUser::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->regularUser->id,
        'is_active' => true,
    ]);

    $branches = $this->service->getAccessibleBranches($this->regularUser);

    expect($branches)->toHaveCount(1)
        ->and($branches->first()->id)->toBe($this->branchA->id);
});

it('returns empty collection for user with no branch assignments', function () {
    $branches = $this->service->getAccessibleBranches($this->regularUser);

    expect($branches)->toHaveCount(0);
});

it('excludes inactive branches from admin accessible branches', function () {
    Branch::create([
        'company_id' => $this->company->id,
        'name' => 'Inactive Branch',
        'code' => 'IB',
        'is_active' => false,
    ]);

    $branches = $this->service->getAccessibleBranches($this->adminUser);

    expect($branches->where('is_active', false))->toHaveCount(0);
});

// --- getEffectiveRole ---

it('returns branch-specific role when set in assignment', function () {
    BranchUser::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->regularUser->id,
        'role_id' => $this->role->id,
        'is_active' => true,
    ]);

    $role = $this->service->getEffectiveRole($this->regularUser, $this->branchA->id);

    expect($role->id)->toBe($this->role->id);
});

it('falls back to company-level role when no branch role is set', function () {
    $companyRole = Role::create([
        'company_id' => $this->company->id,
        'name' => 'Company Role',
        'permissions' => ['reports.view'],
    ]);
    $this->regularUser->roles()->attach($companyRole->id);

    BranchUser::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->regularUser->id,
        'role_id' => null,
        'is_active' => true,
    ]);

    $role = $this->service->getEffectiveRole($this->regularUser, $this->branchA->id);

    expect($role->id)->toBe($companyRole->id);
});

it('returns null when user has no roles and no branch assignment', function () {
    $role = $this->service->getEffectiveRole($this->regularUser, $this->branchA->id);

    expect($role)->toBeNull();
});

// --- getEffectivePermissions ---

it('returns wildcard permissions for admin', function () {
    $permissions = $this->service->getEffectivePermissions($this->adminUser, $this->branchA->id);

    expect($permissions)->toContain('*');
});

it('returns branch role permissions for regular user', function () {
    BranchUser::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->regularUser->id,
        'role_id' => $this->role->id,
        'is_active' => true,
    ]);

    $permissions = $this->service->getEffectivePermissions($this->regularUser, $this->branchA->id);

    expect($permissions)->toBe(['sales.view', 'sales.create']);
});

it('returns empty array when user has no role assigned in branch', function () {
    BranchUser::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->regularUser->id,
        'role_id' => null,
        'is_active' => true,
    ]);

    $permissions = $this->service->getEffectivePermissions($this->regularUser, $this->branchA->id);

    expect($permissions)->toBe([]);
});

// --- assertUserCanAccessBranch ---

it('does not throw when user is authorised for the branch', function () {
    BranchUser::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->regularUser->id,
        'is_active' => true,
    ]);

    expect(fn () => $this->service->assertUserCanAccessBranch($this->regularUser, $this->branchA->id))
        ->not->toThrow(AuthorizationException::class);
});

it('throws AuthorizationException when user is not assigned to the branch', function () {
    expect(fn () => $this->service->assertUserCanAccessBranch($this->regularUser, $this->branchA->id))
        ->toThrow(AuthorizationException::class, 'You do not have access to this branch.');
});

// --- User model helpers ---

it('isAdmin returns true for admin user', function () {
    expect($this->adminUser->isAdmin())->toBeTrue();
});

it('isAdmin returns false for regular user', function () {
    expect($this->regularUser->isAdmin())->toBeFalse();
});

it('canAccessBranch returns true for admin on any branch', function () {
    expect($this->adminUser->canAccessBranch($this->branchB->id))->toBeTrue();
});

it('canAccessBranch returns true for user with active assignment', function () {
    BranchUser::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->regularUser->id,
        'is_active' => true,
    ]);

    expect($this->regularUser->canAccessBranch($this->branchA->id))->toBeTrue();
});

it('canAccessBranch returns false for user without assignment', function () {
    expect($this->regularUser->canAccessBranch($this->branchA->id))->toBeFalse();
});

it('accessibleBranchIds returns all company branches for admin', function () {
    $ids = $this->adminUser->accessibleBranchIds();

    expect($ids)->toContain($this->branchA->id, $this->branchB->id);
});

it('accessibleBranchIds returns only assigned branch ids for regular user', function () {
    BranchUser::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branchB->id,
        'user_id' => $this->regularUser->id,
        'is_active' => true,
    ]);

    $ids = $this->regularUser->accessibleBranchIds()->toArray();

    expect($ids)->toBe([$this->branchB->id])
        ->and($ids)->not->toContain($this->branchA->id);
});
