<?php

use App\Models\Party;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Product;
use App\Models\Employee;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Enums\PartyTypeEnum;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function phase2aWarmAllTablesCache(): void
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
    phase2aWarmAllTablesCache();

    $fy = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $fy->id, 'company_name' => 'Co', 'code' => 'P2-CO',
        'inventory_costing_method' => 'fifo',
    ]);

    $this->branchA = Branch::create([
        'company_id' => $this->company->id, 'name' => 'A', 'code' => 'P2-A',
        'is_head_office' => true, 'is_active' => true,
    ]);
    $this->branchB = Branch::create([
        'company_id' => $this->company->id, 'name' => 'B', 'code' => 'P2-B',
        'is_head_office' => false, 'is_active' => true,
    ]);

    TenantService::setCompanyId($this->company->id);
});

afterEach(fn () => TenantService::reset());

it('auto-stamps the active branch on newly branch-owned master data', function () {
    TenantService::setBranchId($this->branchB->id);

    $warehouse = Warehouse::create(['name' => 'Main', 'code' => 'WH']);
    $party = Party::create(['type' => PartyTypeEnum::CUSTOMER, 'name' => 'Cust', 'code' => 'C1']);
    $product = Product::create(['name' => 'Widget', 'code' => 'W1']);

    expect($warehouse->branch_id)->toBe($this->branchB->id)
        ->and($party->branch_id)->toBe($this->branchB->id)
        ->and($product->branch_id)->toBe($this->branchB->id);
});

it('hides master data belonging to another branch', function (string $model, array $attrs, string $codeField) {
    $model::create([...$attrs, $codeField => 'A1', 'branch_id' => $this->branchA->id]);
    $model::create([...$attrs, $codeField => 'B1', 'branch_id' => $this->branchB->id]);

    TenantService::setBranchId($this->branchA->id);
    expect($model::count())->toBe(1)
        ->and($model::first()->branch_id)->toBe($this->branchA->id);

    TenantService::setBranchId($this->branchB->id);
    expect($model::count())->toBe(1)
        ->and($model::first()->branch_id)->toBe($this->branchB->id);
})->with([
    'warehouse' => [Warehouse::class, ['name' => 'WH'], 'code'],
    'party' => [Party::class, ['type' => PartyTypeEnum::CUSTOMER, 'name' => 'P'], 'code'],
    'product' => [Product::class, ['name' => 'P'], 'code'],
    'employee' => [Employee::class, ['first_name' => 'E', 'last_name' => 'E', 'join_date' => '2026-01-01'], 'employee_code'],
]);

it('scopes product variants to their branch', function () {
    $productA = Product::create(['name' => 'A', 'code' => 'PA', 'branch_id' => $this->branchA->id]);
    $productB = Product::create(['name' => 'B', 'code' => 'PB', 'branch_id' => $this->branchB->id]);

    ProductVariant::create(['product_id' => $productA->id, 'sku' => 'SKA', 'branch_id' => $this->branchA->id]);
    ProductVariant::create(['product_id' => $productB->id, 'sku' => 'SKB', 'branch_id' => $this->branchB->id]);

    TenantService::setBranchId($this->branchA->id);

    expect(ProductVariant::count())->toBe(1)
        ->and(ProductVariant::first()->sku)->toBe('SKA');
});

it('still scopes to all accessible branches for an admin with no branch active', function () {
    Warehouse::create(['name' => 'A', 'code' => 'WA', 'branch_id' => $this->branchA->id]);
    Warehouse::create(['name' => 'B', 'code' => 'WB', 'branch_id' => $this->branchB->id]);

    TenantService::setBranchId(null);

    // No branch selected + no authenticated user => company scope only, both visible.
    expect(Warehouse::count())->toBe(2);
});
