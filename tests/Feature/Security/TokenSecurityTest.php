<?php

use App\Models\User;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Services\TenantService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function tokenSecWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
}

function tokenSecMakeAdminUser(): array
{
    $fy = FiscalYear::create([
        'year_name' => '2026-TS-'.uniqid(),
        'year_code' => '26TS'.uniqid(),
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $company = Company::create([
        'fiscal_year_id' => $fy->id,
        'company_name' => 'Token Sec Co',
        'code' => 'TSC-'.uniqid(),
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
        'is_active' => true,
    ]);

    $admin = User::create([
        'company_id' => $company->id,
        'name' => 'Token Admin',
        'email' => 'tokenadmin-'.uniqid().'@example.com',
        'password' => Hash::make('secret123'),
        'user_type' => UserTypeEnum::ADMIN,
        'status' => true,
    ]);

    return [$company, $admin];
}

beforeEach(function () {
    tokenSecWarmCache();
    TenantService::reset();
});

// --- Item 14: Token scoping ---

it('issues a wildcard token for admin-type users on login', function () {
    [$company, $admin] = tokenSecMakeAdminUser();

    $res = $this->postJson('/api/admin/login', [
        'email' => $admin->email,
        'password' => 'secret123',
    ]);

    $res->assertSuccessful();

    $token = $admin->fresh()->tokens()->latest()->first();
    expect($token)->not->toBeNull()
        ->and($token->abilities)->toBe(['*']);
});

it('falls back to ["user:read"] ability when sub-user has no roles', function () {
    [$company, $admin] = tokenSecMakeAdminUser();

    $subUser = User::create([
        'company_id' => $company->id,
        'name' => 'Roleless User',
        'email' => 'roleless-'.uniqid().'@example.com',
        'password' => Hash::make('secret123'),
        'user_type' => UserTypeEnum::USER,
        'status' => true,
    ]);

    $res = $this->postJson('/api/admin/login', [
        'email' => $subUser->email,
        'password' => 'secret123',
    ]);

    $res->assertSuccessful();

    $token = $subUser->fresh()->tokens()->latest()->first();
    expect($token->abilities)->toBe(['user:read']);
});

// --- Item 14: Revoke all tokens on password change ---

it('revokes all existing tokens and issues a fresh token on password change', function () {
    [$company, $admin] = tokenSecMakeAdminUser();

    // Issue two tokens to simulate multiple active sessions
    $token1 = $admin->createToken('session-1');
    $admin->createToken('session-2');
    $oldTokenIds = [$token1->accessToken->id];

    expect($admin->tokens()->count())->toBe(2);

    $res = $this->withToken($token1->plainTextToken)
        ->putJson('/api/admin/profile/change-password', [
            'current_password' => 'secret123',
            'password' => 'newSecret456!',
            'password_confirmation' => 'newSecret456!',
        ]);

    $res->assertSuccessful()
        ->assertJsonStructure(['access_token', 'expires_at', 'message']);

    // Only the fresh token should remain — both previous tokens are gone
    expect($admin->tokens()->count())->toBe(1);

    // The original session-1 token row must no longer exist in the DB
    foreach ($oldTokenIds as $id) {
        expect(\Laravel\Sanctum\PersonalAccessToken::find($id))->toBeNull();
    }
});

it('returns a usable new token after password change', function () {
    [$company, $admin] = tokenSecMakeAdminUser();

    $oldToken = $admin->createToken('auth-token')->plainTextToken;

    $res = $this->withToken($oldToken)
        ->putJson('/api/admin/profile/change-password', [
            'current_password' => 'secret123',
            'password' => 'brandNew789!',
            'password_confirmation' => 'brandNew789!',
        ]);

    $res->assertSuccessful();
    $newToken = $res->json('access_token');

    $this->withToken($newToken)
        ->getJson('/api/admin/profile')
        ->assertSuccessful();
});

// --- Item 10: permissions endpoint returns user_type ---

it('permissions endpoint includes user_type in response', function () {
    [$company, $admin] = tokenSecMakeAdminUser();
    TenantService::setCompanyId($company->id);

    $token = $admin->createToken('auth-token')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/admin/profile/permissions')
        ->assertSuccessful()
        ->assertJsonStructure(['permissions', 'user_type'])
        ->assertJsonPath('user_type', 'admin');
});
