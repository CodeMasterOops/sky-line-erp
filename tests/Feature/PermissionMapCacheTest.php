<?php

use App\Models\User;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use App\Http\Controllers\Api\Admin\UserManagement\PermissionController;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function permMapWarmAllTablesCache(): void
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
    permMapWarmAllTablesCache();
    Cache::forget(PermissionController::PERMISSION_MAP_CACHE_KEY);

    $fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $fiscalYear->id,
        'company_name' => 'Perm Co',
        'code' => 'PRM',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Perm Admin',
        'email' => 'perm-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

it('returns the permission map and caches it', function () {
    expect(Cache::has(PermissionController::PERMISSION_MAP_CACHE_KEY))->toBeFalse();

    $response = $this->getJson('/api/admin/permission');

    $response->assertSuccessful()
        ->assertJsonStructure(['data' => ['Sales', 'Purchase', 'Inventory', 'Accounting', 'HR']]);

    expect(Cache::has(PermissionController::PERMISSION_MAP_CACHE_KEY))->toBeTrue();
});

it('serves the cached map without rescanning controllers', function () {
    // Prime the cache with a sentinel value; the endpoint must return it
    // verbatim instead of re-running the (expensive) reflection scan.
    Cache::forever(PermissionController::PERMISSION_MAP_CACHE_KEY, ['Sales' => ['sentinel']]);

    $this->getJson('/api/admin/permission')
        ->assertSuccessful()
        ->assertJsonPath('data.Sales', ['sentinel']);
});
