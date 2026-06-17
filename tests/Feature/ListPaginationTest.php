<?php

use App\Models\User;
use App\Models\Brand;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function listPaginationWarmAllTablesCache(): void
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
    listPaginationWarmAllTablesCache();

    $fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $fiscalYear->id,
        'company_name' => 'Pagination Co',
        'code' => 'PGN',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Pagination Admin',
        'email' => 'pgn-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

it('paginates brand index results', function () {
    foreach (range(1, 15) as $i) {
        Brand::create([
            'company_id' => $this->company->id,
            'name' => "Brand {$i}",
            'code' => "BR-{$i}",
        ]);
    }

    $response = $this->getJson('/api/admin/brand?limit=10&page=1');

    $response->assertSuccessful()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonPath('meta.total', 15)
        ->assertJsonPath('meta.current_page', 1);
});

it('paginates stock reconciliation rows', function () {
    $response = $this->getJson('/api/admin/inventory/stock-reconciliation?only_mismatch=0&limit=5&page=1');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data',
            'meta' => [
                'current_page',
                'per_page',
                'total',
                'row_count',
            ],
        ])
        ->assertJsonPath('meta.per_page', 5);
});
