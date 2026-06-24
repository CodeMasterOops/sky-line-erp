<?php

use App\Models\User;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Enums\DateModeEnum;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Services\TenantService;
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
        'code' => 'TC-DM-'.uniqid(),
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'DM Tester',
        'email' => 'dm-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
});

it('defaults a new user to BS date mode', function () {
    expect($this->user->fresh()->date_mode)->toBe(DateModeEnum::Bs);
});

it('exposes the date mode on the profile endpoint', function () {
    $response = $this->getJson('/api/admin/profile');

    $response->assertSuccessful()
        ->assertJsonPath('data.date_mode', DateModeEnum::Bs->value);
});

it('updates the date mode for the authenticated user', function () {
    $response = $this->putJson('/api/admin/profile/date-mode', [
        'date_mode' => DateModeEnum::Ad->value,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.date_mode', DateModeEnum::Ad->value);

    expect($this->user->fresh()->date_mode)->toBe(DateModeEnum::Ad);
});

it('rejects an invalid date mode', function () {
    $response = $this->putJson('/api/admin/profile/date-mode', [
        'date_mode' => 'xx',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('date_mode');
});

it('requires a date mode', function () {
    $response = $this->putJson('/api/admin/profile/date-mode', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('date_mode');
});
