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
use Illuminate\Support\Facades\Queue;
use App\Jobs\DataTransfer\GenerateExportJob;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Cache::forget(allTablesCacheKey());

    $fiscalYear = FiscalYear::create([
        'year_name' => '2026P0',
        'year_code' => '26P0',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $fiscalYear->id,
        'company_name' => 'P0 Security Co',
        'code' => 'P0C'.uniqid(),
    ]);

    $this->branchA = Branch::create([
        'company_id' => $this->company->id,
        'name' => 'Head Office',
        'code' => 'P0HO',
        'is_head_office' => true,
        'is_active' => true,
    ]);

    $this->admin = User::create([
        'company_id' => $this->company->id,
        'name' => 'Admin',
        'email' => 'p0-admin-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Regular',
        'email' => 'p0-user-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::USER,
    ]);

    TenantService::setCompanyId($this->company->id);
});

afterEach(function () {
    TenantService::reset();
});

/**
 * Attach a company-level role carrying the given permissions to the regular
 * user. With no X-Branch-Id header, hasPermission() resolves these.
 *
 * @param  list<string>  $permissions
 */
function giveUserPermissions(User $user, array $permissions): Role
{
    $role = Role::create([
        'company_id' => $user->company_id,
        'name' => 'role-'.uniqid(),
        'permissions' => $permissions,
    ]);

    $user->roles()->attach($role->id);

    return $role;
}

// ───────────────────────── C-1: TaxGroup authorization ─────────────────────────

it('C-1: blocks a permissionless user from listing tax groups', function () {
    Sanctum::actingAs($this->user, [], 'admin');

    $this->getJson('/api/admin/tax-group')->assertForbidden();
});

it('C-1: blocks a permissionless user from creating a tax group', function () {
    Sanctum::actingAs($this->user, [], 'admin');

    $this->postJson('/api/admin/tax-group', ['name' => 'X'])->assertForbidden();
});

it('C-1: allows a user holding list_tax to read tax groups', function () {
    giveUserPermissions($this->user, ['list_tax']);
    Sanctum::actingAs($this->user, [], 'admin');

    $this->getJson('/api/admin/tax-group')->assertSuccessful();
});

it('C-1: allows an admin to read tax groups', function () {
    Sanctum::actingAs($this->admin, [], 'admin');

    $this->getJson('/api/admin/tax-group')->assertSuccessful();
});

// ───────────────────── H-2: role / user privilege escalation ────────────────────

it('H-2: rejects a role granting a permission the actor does not hold', function () {
    giveUserPermissions($this->user, ['create_role']);
    Sanctum::actingAs($this->user, [], 'admin');

    $this->postJson('/api/admin/role', [
        'name' => 'Escalated',
        'permissions' => ['delete_user'],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['permissions.0']);
});

it('H-2: allows a delegated manager to grant only permissions they hold', function () {
    giveUserPermissions($this->user, ['create_role', 'list_tax']);
    Sanctum::actingAs($this->user, [], 'admin');

    $this->postJson('/api/admin/role', [
        'name' => 'Scoped',
        'permissions' => ['list_tax'],
    ])->assertCreated();
});

it('H-2: lets an admin grant any catalogue permission', function () {
    Sanctum::actingAs($this->admin, [], 'admin');

    $this->postJson('/api/admin/role', [
        'name' => 'Powerful',
        'permissions' => ['delete_user'],
    ])->assertCreated();
});

it('H-2: rejects an unknown permission string', function () {
    Sanctum::actingAs($this->admin, [], 'admin');

    $this->postJson('/api/admin/role', [
        'name' => 'Bogus',
        'permissions' => ['totally_made_up_permission'],
    ])->assertStatus(422);
});

it('H-2: prevents assigning a role more powerful than the actor', function () {
    giveUserPermissions($this->user, ['create_user']);

    $powerfulRole = Role::create([
        'company_id' => $this->company->id,
        'name' => 'Super',
        'permissions' => ['delete_user'],
    ]);

    Sanctum::actingAs($this->user, [], 'admin');

    $this->postJson('/api/admin/user', [
        'name' => 'New User',
        'email' => 'new-'.uniqid().'@example.com',
        'roles' => [$powerfulRole->id],
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['roles']);
});

it('H-2: lets an admin assign any role', function () {
    $powerfulRole = Role::create([
        'company_id' => $this->company->id,
        'name' => 'Super',
        'permissions' => ['delete_user'],
    ]);

    Sanctum::actingAs($this->admin, [], 'admin');

    $this->postJson('/api/admin/user', [
        'name' => 'New User',
        'email' => 'new-'.uniqid().'@example.com',
        'roles' => [$powerfulRole->id],
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ])->assertCreated();
});

// ───────────────── H-1: branch isolation for data export / import ────────────────

it('H-1: blocks a non-admin export without a selected branch', function () {
    Queue::fake();
    giveUserPermissions($this->user, ['export_data']);
    Sanctum::actingAs($this->user, [], 'admin');

    $this->postJson('/api/admin/data-transfers/exports', [
        'entity_type' => 'product',
        'format' => 'csv',
    ])->assertStatus(422)
        ->assertJson(['message' => 'Select a branch before importing or exporting data.']);

    Queue::assertNothingPushed();
});

it('H-1: allows an admin export without a branch (consolidated view)', function () {
    Queue::fake();
    Sanctum::actingAs($this->admin, [], 'admin');

    $this->postJson('/api/admin/data-transfers/exports', [
        'entity_type' => 'product',
        'format' => 'csv',
    ])->assertCreated();

    Queue::assertPushed(GenerateExportJob::class);
});

it('H-1: allows a non-admin export once a branch is selected', function () {
    Queue::fake();

    $role = giveUserPermissions($this->user, ['export_data']);

    BranchUser::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branchA->id,
        'user_id' => $this->user->id,
        'role_id' => $role->id,
        'is_active' => true,
    ]);

    Sanctum::actingAs($this->user, [], 'admin');

    $this->postJson('/api/admin/data-transfers/exports', [
        'entity_type' => 'product',
        'format' => 'csv',
    ], ['X-Branch-Id' => (string) $this->branchA->id])
        ->assertCreated();

    Queue::assertPushed(GenerateExportJob::class);
});
