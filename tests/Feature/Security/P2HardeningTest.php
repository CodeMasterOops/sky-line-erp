<?php

use App\Models\Role;
use App\Models\User;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Api\Admin\UserManagement\PermissionController;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Cache::forget(allTablesCacheKey());
    Cache::forget(PermissionController::PERMISSION_MAP_CACHE_KEY);

    $fiscalYear = FiscalYear::create([
        'year_name' => '2026P2',
        'year_code' => '26P2',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $fiscalYear->id,
        'company_name' => 'P2 Co',
        'code' => 'P2C'.uniqid(),
    ]);

    $this->admin = User::create([
        'company_id' => $this->company->id,
        'name' => 'Admin',
        'email' => 'p2-admin-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Regular',
        'email' => 'p2-user-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::USER,
    ]);

    TenantService::setCompanyId($this->company->id);
});

afterEach(function () {
    TenantService::reset();
});

/**
 * @param  list<string>  $permissions
 */
function p2GivePermissions(User $user, array $permissions): void
{
    $role = Role::create([
        'company_id' => $user->company_id,
        'name' => 'role-'.uniqid(),
        'permissions' => $permissions,
    ]);

    $user->roles()->attach($role->id);
}

// ───────────────── M-2: permission catalogue completeness ─────────────────

it('M-2: surfaces permissions previously hidden from the role-management UI', function () {
    Sanctum::actingAs($this->admin, [], 'admin');

    $response = $this->getJson('/api/admin/permission')->assertSuccessful();

    $data = $response->json('data');
    $values = [];
    array_walk_recursive($data, function ($value, $key) use (&$values) {
        if ($key === 'permission') {
            $values[] = $value;
        }
    });

    // These live in dirs / top-level controllers the catalogue used to skip
    // (Party, Pos, Nepal/TdsReceivable, DataTransfer).
    expect($values)
        ->toContain('list_party')
        ->toContain('list_pos')
        ->toContain('list_tds_receivable')
        ->toContain('list_data_transfer');
});

it('M-2: still groups the original modules', function () {
    Sanctum::actingAs($this->admin, [], 'admin');

    $this->getJson('/api/admin/permission')
        ->assertSuccessful()
        ->assertJsonStructure(['data' => ['Sales', 'Purchase', 'Inventory', 'Accounting', 'HR']]);
});

// ───────────────── L-1: gate the previously-open endpoints ─────────────────

it('L-1: blocks the permission catalogue for a user without role permissions', function () {
    Sanctum::actingAs($this->user, [], 'admin');

    $this->getJson('/api/admin/permission')->assertForbidden();
});

it('L-1: allows the permission catalogue for a user who can manage roles', function () {
    p2GivePermissions($this->user, ['list_role']);
    Sanctum::actingAs($this->user, [], 'admin');

    $this->getJson('/api/admin/permission')->assertSuccessful();
});

it('L-1: blocks barcode generation without product access', function () {
    Sanctum::actingAs($this->user, [], 'admin');

    $this->postJson('/api/admin/barcode/preview', [
        'value' => 'SKU-1',
        'format' => 'code128',
    ])->assertForbidden();
});

it('L-1: allows barcode generation with list_product', function () {
    p2GivePermissions($this->user, ['list_product']);
    Sanctum::actingAs($this->user, [], 'admin');

    $this->postJson('/api/admin/barcode/preview', [
        'value' => 'SKU-1',
        'format' => 'code128',
    ])->assertSuccessful();
});
