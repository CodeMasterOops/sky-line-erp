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

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Cache::forget(allTablesCacheKey());

    $fiscalYear = FiscalYear::create([
        'year_name' => '2026BU',
        'year_code' => '26BU',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $fiscalYear->id,
        'company_name' => 'Branch User Test Co',
        'code' => 'BUC'.uniqid(),
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'name' => 'Test Branch',
        'code' => 'BUTB',
        'is_head_office' => true,
        'is_active' => true,
    ]);

    $this->adminUser = User::create([
        'company_id' => $this->company->id,
        'name' => 'Admin User',
        'email' => 'admin-bu-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->regularUser = User::create([
        'company_id' => $this->company->id,
        'name' => 'Regular User',
        'email' => 'user-bu-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::USER,
    ]);

    $this->role = Role::create([
        'company_id' => $this->company->id,
        'name' => 'Sales Rep',
        'permissions' => ['sales.view', 'sales.create'],
    ]);
});

afterEach(function () {
    TenantService::setBranchId(null);
    TenantService::setBranchRole(null);
    TenantService::setBranchPermissions(null);
});

// --- GET branch/{branch}/users ---

it('admin can list branch user assignments', function () {
    BranchUser::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->regularUser->id,
        'role_id' => $this->role->id,
        'is_active' => true,
    ]);

    Sanctum::actingAs($this->adminUser, [], 'admin');

    $this->getJson("/api/admin/branch/{$this->branch->id}/users")
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $this->regularUser->id)
        ->assertJsonPath('data.0.name', $this->regularUser->name)
        ->assertJsonPath('data.0.is_active', true);
});

it('regular user cannot list branch assignments', function () {
    Sanctum::actingAs($this->regularUser, [], 'admin');

    $this->getJson("/api/admin/branch/{$this->branch->id}/users")
        ->assertForbidden();
});

// --- POST branch/{branch}/users ---

it('admin can assign a user to a branch', function () {
    Sanctum::actingAs($this->adminUser, [], 'admin');

    $this->postJson("/api/admin/branch/{$this->branch->id}/users", [
        'user_id' => $this->regularUser->id,
        'role_id' => $this->role->id,
    ])->assertCreated()
        ->assertJsonPath('data.id', $this->regularUser->id)
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('message', 'User assigned to branch successfully.');

    $this->assertDatabaseHas('branch_users', [
        'branch_id' => $this->branch->id,
        'user_id' => $this->regularUser->id,
        'role_id' => $this->role->id,
        'is_active' => true,
    ]);
});

it('returns 422 when assigning a user already assigned to the branch', function () {
    BranchUser::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->regularUser->id,
        'is_active' => true,
    ]);

    Sanctum::actingAs($this->adminUser, [], 'admin');

    $this->postJson("/api/admin/branch/{$this->branch->id}/users", [
        'user_id' => $this->regularUser->id,
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'User is already assigned to this branch.');
});

it('rejects assignment for a user from another company', function () {
    $otherFiscalYear = FiscalYear::create([
        'year_name' => '2026X',
        'year_code' => '26X',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $otherCompany = Company::create([
        'fiscal_year_id' => $otherFiscalYear->id,
        'company_name' => 'Other Co',
        'code' => 'XCO'.uniqid(),
    ]);

    $outsider = User::withoutGlobalScopes()->create([
        'company_id' => $otherCompany->id,
        'name' => 'Outsider',
        'email' => 'outsider-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::USER,
    ]);

    Sanctum::actingAs($this->adminUser, [], 'admin');

    $this->postJson("/api/admin/branch/{$this->branch->id}/users", [
        'user_id' => $outsider->id,
    ])->assertUnprocessable();
});

it('validates that user_id is required', function () {
    Sanctum::actingAs($this->adminUser, [], 'admin');

    $this->postJson("/api/admin/branch/{$this->branch->id}/users", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_id']);
});

it('regular user cannot assign users to a branch', function () {
    Sanctum::actingAs($this->regularUser, [], 'admin');

    $this->postJson("/api/admin/branch/{$this->branch->id}/users", [
        'user_id' => $this->regularUser->id,
    ])->assertForbidden();
});

// --- PATCH branch/{branch}/users/{user} ---

it('admin can update a branch assignment role and status', function () {
    BranchUser::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->regularUser->id,
        'is_active' => true,
    ]);

    Sanctum::actingAs($this->adminUser, [], 'admin');

    $this->patchJson("/api/admin/branch/{$this->branch->id}/users/{$this->regularUser->id}", [
        'user_id' => $this->regularUser->id,
        'role_id' => $this->role->id,
        'is_active' => false,
    ])->assertSuccessful()
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('message', 'Branch assignment updated successfully.');

    $this->assertDatabaseHas('branch_users', [
        'branch_id' => $this->branch->id,
        'user_id' => $this->regularUser->id,
        'role_id' => $this->role->id,
        'is_active' => false,
    ]);
});

it('returns 404 when updating a non-existent assignment', function () {
    Sanctum::actingAs($this->adminUser, [], 'admin');

    $this->patchJson("/api/admin/branch/{$this->branch->id}/users/{$this->regularUser->id}", [
        'user_id' => $this->regularUser->id,
        'is_active' => false,
    ])->assertNotFound();
});

it('regular user cannot update branch assignments', function () {
    BranchUser::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->regularUser->id,
        'is_active' => true,
    ]);

    Sanctum::actingAs($this->regularUser, [], 'admin');

    $this->patchJson("/api/admin/branch/{$this->branch->id}/users/{$this->regularUser->id}", [
        'user_id' => $this->regularUser->id,
        'is_active' => false,
    ])->assertForbidden();
});

// --- DELETE branch/{branch}/users/{user} ---

it('admin can remove a user from a branch', function () {
    BranchUser::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->regularUser->id,
        'is_active' => true,
    ]);

    Sanctum::actingAs($this->adminUser, [], 'admin');

    $this->deleteJson("/api/admin/branch/{$this->branch->id}/users/{$this->regularUser->id}")
        ->assertSuccessful()
        ->assertJsonPath('message', 'User removed from branch successfully.');

    $this->assertDatabaseMissing('branch_users', [
        'branch_id' => $this->branch->id,
        'user_id' => $this->regularUser->id,
    ]);
});

it('returns 404 when removing a user not assigned to the branch', function () {
    Sanctum::actingAs($this->adminUser, [], 'admin');

    $this->deleteJson("/api/admin/branch/{$this->branch->id}/users/{$this->regularUser->id}")
        ->assertNotFound();
});

it('regular user cannot remove users from a branch', function () {
    BranchUser::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->regularUser->id,
        'is_active' => true,
    ]);

    Sanctum::actingAs($this->regularUser, [], 'admin');

    $this->deleteJson("/api/admin/branch/{$this->branch->id}/users/{$this->regularUser->id}")
        ->assertForbidden();
});

// --- GET branch/my-branches ---

it('returns all active branches for admin user', function () {
    Sanctum::actingAs($this->adminUser, [], 'admin');

    $response = $this->getJson('/api/admin/branch/my-branches');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('default_branch_id', $this->branch->id);
});

it('returns only assigned branches for regular user', function () {
    $branchTwo = Branch::create([
        'company_id' => $this->company->id,
        'name' => 'Branch Two',
        'code' => 'BT2',
        'is_head_office' => false,
        'is_active' => true,
    ]);

    BranchUser::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->regularUser->id,
        'is_active' => true,
    ]);

    Sanctum::actingAs($this->regularUser, [], 'admin');

    $response = $this->getJson('/api/admin/branch/my-branches');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->branch->id);
});

// --- GET profile/permissions ---

it('returns company permissions when no branch context is set', function () {
    $this->regularUser->roles()->attach($this->role->id);

    Sanctum::actingAs($this->regularUser, [], 'admin');

    $response = $this->getJson('/api/admin/profile/permissions');

    $response->assertSuccessful()
        ->assertJsonStructure(['permissions']);

    $decoded = json_decode(base64_decode($response->json('permissions')), true);
    expect($decoded)->toContain('sales.view');
});

it('returns branch-specific permissions when X-Branch-Id header is sent', function () {
    $branchRole = Role::create([
        'company_id' => $this->company->id,
        'name' => 'Branch Viewer',
        'permissions' => ['reports.view'],
    ]);

    BranchUser::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->regularUser->id,
        'role_id' => $branchRole->id,
        'is_active' => true,
    ]);

    Sanctum::actingAs($this->regularUser, [], 'admin');

    $response = $this->getJson('/api/admin/profile/permissions', [
        'X-Branch-Id' => (string) $this->branch->id,
    ]);

    $response->assertSuccessful();

    $decoded = json_decode(base64_decode($response->json('permissions')), true);
    expect($decoded)->toContain('reports.view');
});
