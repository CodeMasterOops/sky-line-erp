<?php

use App\Models\User;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Services\TenantService;
use App\Models\SecurityActivity;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);

    TenantService::setCompanyId(null);
    TenantService::setBranchId(null);

    $fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $fiscalYear->id,
        'company_name' => 'Test Co',
        'code' => 'TC-SEC-'.uniqid(),
        'is_active' => true,
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Sec Tester',
        'email' => 'sec-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);
});

/**
 * Create a second active admin so the last-admin guardrail allows the action.
 */
function secondAdmin(Company $company): User
{
    return User::create([
        'company_id' => $company->id,
        'name' => 'Other Admin',
        'email' => 'other-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);
}

// ─── Account Activity ──────────────────────────────────────────────────────

it('returns only the caller security activity', function () {
    SecurityActivity::create(['company_id' => $this->company->id, 'user_id' => $this->user->id, 'event' => 'login']);
    $other = secondAdmin($this->company);
    SecurityActivity::create(['company_id' => $this->company->id, 'user_id' => $other->id, 'event' => 'login']);

    Sanctum::actingAs($this->user, ['*'], 'admin');

    $response = $this->getJson('/api/admin/profile/security/activity');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.event'))->toBe('login')
        ->and($response->json('data.0.label'))->toBe('Signed in');
});

// ─── Device Management ─────────────────────────────────────────────────────

it('lists devices and flags the current one', function () {
    $current = $this->user->createToken('auth-token', ['*'])->plainTextToken;
    $this->user->createToken('auth-token', ['*']); // a second device

    $response = $this->withToken($current)->getJson('/api/admin/profile/security/devices');

    $response->assertSuccessful();
    $devices = collect($response->json('data'));
    expect($devices)->toHaveCount(2)
        ->and($devices->where('is_current', true))->toHaveCount(1);
});

it('revokes another device and logs it', function () {
    $current = $this->user->createToken('auth-token', ['*'])->plainTextToken;
    $otherTokenId = $this->user->createToken('auth-token', ['*'])->accessToken->id;

    $response = $this->withToken($current)
        ->deleteJson("/api/admin/profile/security/devices/{$otherTokenId}");

    $response->assertSuccessful();
    expect($this->user->tokens()->whereKey($otherTokenId)->exists())->toBeFalse();
    $this->assertDatabaseHas('security_activities', [
        'user_id' => $this->user->id,
        'event' => 'device_revoked',
    ]);
});

it('cannot revoke the current device', function () {
    $tokenData = $this->user->createToken('auth-token', ['*']);
    $currentId = $tokenData->accessToken->id;

    $response = $this->withToken($tokenData->plainTextToken)
        ->deleteJson("/api/admin/profile/security/devices/{$currentId}");

    $response->assertStatus(422);
    expect($this->user->tokens()->whereKey($currentId)->exists())->toBeTrue();
});

it('returns 404 revoking a device that is not yours', function () {
    $current = $this->user->createToken('auth-token', ['*'])->plainTextToken;
    $stranger = secondAdmin($this->company);
    $strangerTokenId = $stranger->createToken('auth-token', ['*'])->accessToken->id;

    $response = $this->withToken($current)
        ->deleteJson("/api/admin/profile/security/devices/{$strangerTokenId}");

    $response->assertNotFound();
    expect($stranger->tokens()->whereKey($strangerTokenId)->exists())->toBeTrue();
});

it('signs out all other devices but keeps the current one', function () {
    $current = $this->user->createToken('auth-token', ['*'])->plainTextToken;
    $this->user->createToken('auth-token', ['*']);
    $this->user->createToken('auth-token', ['*']);

    $response = $this->withToken($current)->deleteJson('/api/admin/profile/security/devices');

    $response->assertSuccessful();
    expect($this->user->tokens()->count())->toBe(1);
});

// ─── Deactivate ────────────────────────────────────────────────────────────

it('rejects deactivation with a wrong password', function () {
    secondAdmin($this->company);
    Sanctum::actingAs($this->user, ['*'], 'admin');

    $response = $this->postJson('/api/admin/profile/security/deactivate', [
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422);
    expect($this->user->fresh()->status)->toBeTrue();
});

it('deactivates the account, revokes tokens and logs it', function () {
    secondAdmin($this->company);
    $this->user->createToken('auth-token', ['*']);
    Sanctum::actingAs($this->user, ['*'], 'admin');

    $response = $this->postJson('/api/admin/profile/security/deactivate', [
        'password' => 'password',
    ]);

    $response->assertSuccessful();
    expect($this->user->fresh()->status)->toBeFalse()
        ->and($this->user->tokens()->count())->toBe(0);
    $this->assertDatabaseHas('security_activities', [
        'user_id' => $this->user->id,
        'event' => 'deactivated',
    ]);
});

it('blocks deactivating the only active administrator', function () {
    Sanctum::actingAs($this->user, ['*'], 'admin');

    $response = $this->postJson('/api/admin/profile/security/deactivate', [
        'password' => 'password',
    ]);

    $response->assertStatus(422);
    expect($this->user->fresh()->status)->toBeTrue();
});

// ─── Delete ────────────────────────────────────────────────────────────────

it('requires the DELETE confirmation to delete the account', function () {
    secondAdmin($this->company);
    Sanctum::actingAs($this->user, ['*'], 'admin');

    $response = $this->deleteJson('/api/admin/profile/security/account', [
        'password' => 'password',
        'confirmation' => 'nope',
    ]);

    $response->assertStatus(422);
    expect(User::whereKey($this->user->id)->exists())->toBeTrue();
});

it('soft deletes the account when confirmed', function () {
    secondAdmin($this->company);
    $this->user->createToken('auth-token', ['*']);
    Sanctum::actingAs($this->user, ['*'], 'admin');

    $response = $this->deleteJson('/api/admin/profile/security/account', [
        'password' => 'password',
        'confirmation' => 'DELETE',
    ]);

    $response->assertSuccessful();
    $this->assertSoftDeleted('users', ['id' => $this->user->id]);
    expect($this->user->tokens()->count())->toBe(0);
    $this->assertDatabaseHas('security_activities', [
        'user_id' => $this->user->id,
        'event' => 'account_deleted',
    ]);
});

it('blocks deleting the only active administrator', function () {
    Sanctum::actingAs($this->user, ['*'], 'admin');

    $response = $this->deleteJson('/api/admin/profile/security/account', [
        'password' => 'password',
        'confirmation' => 'DELETE',
    ]);

    $response->assertStatus(422);
    $this->assertDatabaseHas('users', ['id' => $this->user->id, 'deleted_at' => null]);
});

// ─── Login stamps device + logs activity ───────────────────────────────────

it('stamps device info and logs activity on login', function () {
    $response = $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120'])
        ->postJson('/api/admin/login', [
            'email' => $this->user->email,
            'password' => 'password',
        ]);

    $response->assertSuccessful();
    $this->assertDatabaseHas('security_activities', [
        'user_id' => $this->user->id,
        'event' => 'login',
    ]);
    expect($this->user->tokens()->first()->user_agent)->toContain('Chrome');
});
