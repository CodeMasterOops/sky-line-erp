<?php

use App\Models\Branch;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\FixedAsset;
use App\Traits\BranchTenant;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function phase2bWarmAllTablesCache(): void
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
    phase2bWarmAllTablesCache();

    $fy = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);
    $this->company = Company::create([
        'fiscal_year_id' => $fy->id, 'company_name' => 'Co', 'code' => 'P2B-CO',
        'inventory_costing_method' => 'fifo',
    ]);
    $this->branchA = Branch::create([
        'company_id' => $this->company->id, 'name' => 'A', 'code' => 'P2B-A',
        'is_head_office' => true, 'is_active' => true,
    ]);
    $this->branchB = Branch::create([
        'company_id' => $this->company->id, 'name' => 'B', 'code' => 'P2B-B',
        'is_head_office' => false, 'is_active' => true,
    ]);

    TenantService::setCompanyId($this->company->id);
});

afterEach(fn () => TenantService::reset());

it('every branch-owned model has a branch_id column and the BranchTenant trait', function () {
    foreach (config('tenancy.branch_owned') as $model) {
        $table = (new $model)->getTable();

        expect(Schema::hasColumn($table, 'branch_id'))->toBeTrue("{$model} ({$table}) is missing a branch_id column");
        expect(in_array(BranchTenant::class, class_uses_recursive($model), true))->toBeTrue("{$model} is missing the BranchTenant trait");
    }
});

it('branch-owned-via-parent children carry no branch scope of their own', function () {
    foreach (array_keys(config('tenancy.branch_owned_via_parent')) as $model) {
        expect(in_array(BranchTenant::class, class_uses_recursive($model), true))
            ->toBeFalse("{$model} should be scoped through its parent, not carry its own BranchTenant trait");
    }
});

it('auto-stamps and branch-scopes a Phase 2b model (FixedAsset)', function () {
    TenantService::setBranchId($this->branchA->id);

    $a = FixedAsset::create(['asset_code' => 'FA-A', 'name' => 'Printer', 'purchase_date' => '2026-01-01']);
    expect($a->branch_id)->toBe($this->branchA->id);

    FixedAsset::create([
        'asset_code' => 'FA-B', 'name' => 'Laptop', 'purchase_date' => '2026-01-01',
        'branch_id' => $this->branchB->id,
    ]);

    expect(FixedAsset::count())->toBe(1)
        ->and(FixedAsset::first()->asset_code)->toBe('FA-A');

    TenantService::setBranchId($this->branchB->id);
    expect(FixedAsset::count())->toBe(1)
        ->and(FixedAsset::first()->asset_code)->toBe('FA-B');
});
