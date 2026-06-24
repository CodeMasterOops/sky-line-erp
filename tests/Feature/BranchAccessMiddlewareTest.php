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
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Http\Events\RequestHandled;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Cache::forget(allTablesCacheKey());

    $fiscalYear = FiscalYear::create([
        'year_name' => '2026MW',
        'year_code' => '26MW',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $fiscalYear->id,
        'company_name' => 'Middleware Test Co',
        'code' => 'MWC'.uniqid(),
    ]);

    $this->branchA = Branch::create([
        'company_id' => $this->company->id,
        'name' => 'Head Office',
        'code' => 'MWHO',
        'is_head_office' => true,
        'is_active' => true,
    ]);

    $this->branchB = Branch::create([
        'company_id' => $this->company->id,
        'name' => 'Branch B',
        'code' => 'MWBB',
        'is_head_office' => false,
        'is_active' => true,
    ]);

    $otherFiscalYear = FiscalYear::create([
        'year_name' => '2026OT',
        'year_code' => '26OT',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->otherCompany = Company::create([
        'fiscal_year_id' => $otherFiscalYear->id,
        'company_name' => 'Other Co',
        'code' => 'OTC'.uniqid(),
    ]);

    $this->otherBranch = Branch::withoutGlobalScopes()->create([
        'company_id' => $this->otherCompany->id,
        'name' => 'Other Branch',
        'code' => 'OTBR',
        'is_head_office' => true,
        'is_active' => true,
    ]);

    $this->adminUser = User::create([
        'company_id' => $this->company->id,
        'name' => 'Admin User',
        'email' => 'admin-mw-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->regularUser = User::create([
        'company_id' => $this->company->id,
        'name' => 'Regular User',
        'email' => 'user-mw-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::USER,
    ]);

    $this->role = Role::create([
        'company_id' => $this->company->id,
        'name' => 'Cashier',
        'permissions' => ['sales.view'],
    ]);
});

afterEach(function () {
    TenantService::setBranchId(null);
    TenantService::setBranchRole(null);
    TenantService::setBranchPermissions(null);
});

it('succeeds without X-Branch-Id and does not set branch context', function () {
    Sanctum::actingAs($this->adminUser, [], 'admin');

    $this->getJson('/api/admin/branch/my-branches')
        ->assertSuccessful();
});

it('accepts a valid branch belonging to the user company', function () {
    Sanctum::actingAs($this->adminUser, [], 'admin');

    $this->getJson('/api/admin/branch/my-branches', [
        'X-Branch-Id' => (string) $this->branchA->id,
    ])->assertSuccessful();
});

it('rejects a branch from a different company with 403', function () {
    Sanctum::actingAs($this->adminUser, [], 'admin');

    $this->getJson('/api/admin/branch/my-branches', [
        'X-Branch-Id' => (string) $this->otherBranch->id,
    ])->assertForbidden();
});

it('rejects a non-existent branch id with 403', function () {
    Sanctum::actingAs($this->adminUser, [], 'admin');

    $this->getJson('/api/admin/branch/my-branches', [
        'X-Branch-Id' => '99999',
    ])->assertForbidden();
});

it('returns the exact branch-context 403 message the frontend matches on', function () {
    // Contract: resources/js/helpers/api.js clears the branch cookie and
    // re-prompts only when the message is exactly this. Keep them in sync.
    Sanctum::actingAs($this->adminUser, [], 'admin');

    $this->getJson('/api/admin/branch/my-branches', [
        'X-Branch-Id' => (string) $this->otherBranch->id,
    ])->assertForbidden()
        ->assertJson(['message' => 'You do not have access to this branch.']);
});

it('blocks unassigned branch for regular user', function () {
    Sanctum::actingAs($this->regularUser, [], 'admin');

    $this->getJson('/api/admin/branch/my-branches', [
        'X-Branch-Id' => (string) $this->branchA->id,
    ])->assertForbidden();
});

it('allows admin to access any branch in the company', function () {
    Sanctum::actingAs($this->adminUser, [], 'admin');

    $this->getJson('/api/admin/branch/my-branches', [
        'X-Branch-Id' => (string) $this->branchB->id,
    ])->assertSuccessful();
});

it('allows regular user with an active branch assignment', function () {
    BranchUser::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->regularUser->id,
        'is_active' => true,
    ]);

    Sanctum::actingAs($this->regularUser, [], 'admin');

    $this->getJson('/api/admin/branch/my-branches', [
        'X-Branch-Id' => (string) $this->branchA->id,
    ])->assertSuccessful();
});

it('blocks regular user with an inactive branch assignment', function () {
    BranchUser::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->regularUser->id,
        'is_active' => false,
    ]);

    Sanctum::actingAs($this->regularUser, [], 'admin');

    $this->getJson('/api/admin/branch/my-branches', [
        'X-Branch-Id' => (string) $this->branchA->id,
    ])->assertForbidden();
});

it('sets branch-specific permissions in TenantService when valid header provided', function () {
    $this->regularUser->roles()->attach($this->role->id);

    BranchUser::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->regularUser->id,
        'role_id' => $this->role->id,
        'is_active' => true,
    ]);

    Sanctum::actingAs($this->regularUser, [], 'admin');

    // Capture TenantService state during the request (before terminate() resets it).
    // RequestHandled fires after response is built but before middleware::terminate().
    $capturedRoleId = null;
    $capturedPermissions = null;
    Event::listen(RequestHandled::class, function () use (&$capturedRoleId, &$capturedPermissions) {
        $capturedRoleId = TenantService::branchRoleId();
        $capturedPermissions = TenantService::branchPermissions();
    });

    $this->getJson('/api/admin/branch/my-branches', [
        'X-Branch-Id' => (string) $this->branchA->id,
    ])->assertSuccessful();

    expect($capturedRoleId)->toBe($this->role->id)
        ->and($capturedPermissions)->toContain('sales.view');
});
