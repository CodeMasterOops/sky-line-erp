<?php

use App\Models\User;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\SuperAdmin;
use App\Enums\UserTypeEnum;
use App\Services\TenantService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function changePasswordWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
}

function changePasswordMakeAdminUser(): array
{
    $fy = FiscalYear::create([
        'year_name' => '2026-CP-'.uniqid(),
        'year_code' => '26CP'.uniqid(),
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $company = Company::create([
        'fiscal_year_id' => $fy->id,
        'company_name' => 'Change Password Co',
        'code' => 'CPC-'.uniqid(),
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
        'is_active' => true,
    ]);

    $admin = User::create([
        'company_id' => $company->id,
        'name' => 'Company Admin',
        'email' => 'cp-admin-'.uniqid().'@example.com',
        'password' => 'secret123',
        'user_type' => UserTypeEnum::ADMIN,
        'status' => true,
    ]);

    return [$company, $admin];
}

beforeEach(function () {
    changePasswordWarmCache();
    TenantService::reset();
});

it('rejects the old company password after a profile password change', function () {
    [$company, $admin] = changePasswordMakeAdminUser();
    $token = $admin->createToken('auth-token')->plainTextToken;

    $this->withToken($token)
        ->putJson('/api/admin/profile/change-password', [
            'current_password' => 'secret123',
            'password' => 'brandNew789!',
            'password_confirmation' => 'brandNew789!',
        ])
        ->assertSuccessful()
        ->assertJsonMissingPath('access_token');

    auth()->forgetGuards();

    $this->withToken($token)
        ->getJson('/api/admin/profile')
        ->assertUnauthorized();

    $this->postJson('/api/admin/login', [
        'email' => $admin->email,
        'password' => 'secret123',
    ])->assertUnprocessable();

    $this->postJson('/api/admin/login', [
        'email' => $admin->email,
        'password' => 'brandNew789!',
    ])->assertSuccessful();

    expect(Hash::check('brandNew789!', $admin->fresh()->password))->toBeTrue()
        ->and(Hash::check('secret123', $admin->fresh()->password))->toBeFalse();
});

it('rejects the old super admin password after a profile password change', function () {
    $superAdmin = SuperAdmin::create([
        'name' => 'Super Admin',
        'email' => 'cp-super-'.uniqid().'@example.com',
        'password' => 'secret123',
    ]);

    $token = $superAdmin->createToken('auth-token')->plainTextToken;

    $this->withToken($token)
        ->putJson('/api/super-admin/profile/change-password', [
            'current_password' => 'secret123',
            'password' => 'brandNew789!',
            'password_confirmation' => 'brandNew789!',
        ])
        ->assertSuccessful()
        ->assertJsonMissingPath('access_token');

    auth()->forgetGuards();

    $this->withToken($token)
        ->getJson('/api/super-admin/profile')
        ->assertUnauthorized();

    $this->postJson('/api/super-admin/login', [
        'email' => $superAdmin->email,
        'password' => 'secret123',
    ])->assertUnprocessable();

    $this->postJson('/api/super-admin/login', [
        'email' => $superAdmin->email,
        'password' => 'brandNew789!',
    ])->assertSuccessful();

    expect(Hash::check('brandNew789!', $superAdmin->fresh()->password))->toBeTrue()
        ->and(Hash::check('secret123', $superAdmin->fresh()->password))->toBeFalse();
});

it('revokes super admin sessions after a password change', function () {
    $superAdmin = SuperAdmin::create([
        'name' => 'Super Admin',
        'email' => 'cp-super-session-'.uniqid().'@example.com',
        'password' => 'secret123',
    ]);

    $oldToken = $superAdmin->createToken('session-1');
    $superAdmin->createToken('session-2');

    $this->withToken($oldToken->plainTextToken)
        ->putJson('/api/super-admin/profile/change-password', [
            'current_password' => 'secret123',
            'password' => 'brandNew789!',
            'password_confirmation' => 'brandNew789!',
        ])
        ->assertSuccessful()
        ->assertJsonMissingPath('access_token');

    expect($superAdmin->tokens()->count())->toBe(0)
        ->and(PersonalAccessToken::find($oldToken->accessToken->id))->toBeNull();
});

it('rejects the old company password after a super admin reset', function () {
    [$company, $admin] = changePasswordMakeAdminUser();
    $admin->createToken('existing-session');

    $superAdmin = SuperAdmin::create([
        'name' => 'Super Admin',
        'email' => 'cp-super-reset-'.uniqid().'@example.com',
        'password' => 'secret123',
    ]);

    $this->withToken($superAdmin->createToken('auth-token')->plainTextToken)
        ->putJson("/api/super-admin/company/{$company->id}/reset-password", [
            'password' => 'resetPass789!',
            'password_confirmation' => 'resetPass789!',
        ])
        ->assertSuccessful();

    expect($admin->fresh()->tokens()->count())->toBe(0);

    $this->postJson('/api/admin/login', [
        'email' => $admin->email,
        'password' => 'secret123',
    ])->assertUnprocessable();

    $this->postJson('/api/admin/login', [
        'email' => $admin->email,
        'password' => 'resetPass789!',
    ])->assertSuccessful();
});

it('returns not found when resetting a company that has no admin user', function () {
    $fy = FiscalYear::create([
        'year_name' => '2026-CP-NA-'.uniqid(),
        'year_code' => '26NA'.uniqid(),
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $company = Company::create([
        'fiscal_year_id' => $fy->id,
        'company_name' => 'No Admin Co',
        'code' => 'NAC-'.uniqid(),
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
        'is_active' => true,
    ]);

    $superAdmin = SuperAdmin::create([
        'name' => 'Super Admin',
        'email' => 'cp-super-noadmin-'.uniqid().'@example.com',
        'password' => 'secret123',
    ]);

    $this->withToken($superAdmin->createToken('auth-token')->plainTextToken)
        ->putJson("/api/super-admin/company/{$company->id}/reset-password", [
            'password' => 'resetPass789!',
            'password_confirmation' => 'resetPass789!',
        ])
        ->assertNotFound();
});
