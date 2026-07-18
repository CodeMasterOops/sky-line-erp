<?php

use App\Models\User;
use App\Models\Company;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Test Co',
        'code' => 'TC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Tester',
        'email' => 'tester-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
});

function makeWarehouse(int $companyId, string $name, ?int $parentId = null): Warehouse
{
    return Warehouse::create([
        'company_id' => $companyId,
        'parent_id' => $parentId,
        'name' => $name,
        'code' => strtoupper(str_replace(' ', '-', $name)),
    ]);
}

it('paginates on root warehouses and reports the root count as the total', function () {
    foreach (range(1, 3) as $i) {
        makeWarehouse($this->company->id, "Root {$i}");
    }

    $response = $this->getJson('/api/admin/warehouse?limit=2');

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(3);
    expect($response->json('meta.per_page'))->toBe(2);
    expect($response->json('meta.last_page'))->toBe(2);
});

it('includes the full descendant subtree for the roots on the current page', function () {
    $root = makeWarehouse($this->company->id, 'Root');
    $child = makeWarehouse($this->company->id, 'Child', $root->id);
    $grandchild = makeWarehouse($this->company->id, 'Grandchild', $child->id);

    $response = $this->getJson('/api/admin/warehouse?limit=25');

    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($root->id)
        ->toContain($child->id)
        ->toContain($grandchild->id);

    expect($response->json('meta.total'))->toBe(1);
});

it('orders root warehouses by code', function () {
    makeWarehouse($this->company->id, 'Zephyr Warehouse');   // code ZEPHYR-WAREHOUSE
    makeWarehouse($this->company->id, 'Alpha Warehouse');    // code ALPHA-WAREHOUSE

    $response = $this->getJson('/api/admin/warehouse?limit=25');

    $response->assertOk();

    $codes = collect($response->json('data'))->pluck('code')->all();

    expect($codes)->toBe(['ALPHA-WAREHOUSE', 'ZEPHYR-WAREHOUSE']);
});

it('does not leak descendants of roots on other pages', function () {
    $rootA = makeWarehouse($this->company->id, 'A Root');
    makeWarehouse($this->company->id, 'A Child', $rootA->id);

    $rootB = makeWarehouse($this->company->id, 'B Root');
    $bChild = makeWarehouse($this->company->id, 'B Child', $rootB->id);

    $response = $this->getJson('/api/admin/warehouse?limit=1&page=1');

    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($rootA->id)
        ->not->toContain($rootB->id)
        ->not->toContain($bChild->id);
});
