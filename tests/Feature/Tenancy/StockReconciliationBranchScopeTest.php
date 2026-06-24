<?php

use App\Models\User;
use App\Models\Stock;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function stockReconWarmCache(): void
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
    stockReconWarmCache();

    $fy = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);
    $this->company = Company::create([
        'fiscal_year_id' => $fy->id, 'company_name' => 'Co', 'code' => 'SR-CO',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);
    $this->user = User::create([
        'company_id' => $this->company->id, 'name' => 'Admin', 'email' => 'sr-'.uniqid().'@t.test',
        'password' => bcrypt('x'), 'user_type' => UserTypeEnum::ADMIN,
    ]);
    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);

    $this->branchA = Branch::create(['company_id' => $this->company->id, 'name' => 'A', 'code' => 'SR-A', 'is_head_office' => true, 'is_active' => true]);
    $this->branchB = Branch::create(['company_id' => $this->company->id, 'name' => 'B', 'code' => 'SR-B', 'is_active' => true]);

    $whA = Warehouse::create(['company_id' => $this->company->id, 'branch_id' => $this->branchA->id, 'name' => 'WH-A', 'code' => 'WHA']);
    $whB = Warehouse::create(['company_id' => $this->company->id, 'branch_id' => $this->branchB->id, 'name' => 'WH-B', 'code' => 'WHB']);

    $product = Product::create(['company_id' => $this->company->id, 'branch_id' => $this->branchA->id, 'name' => 'Widget', 'code' => 'SR-W']);
    $variant = ProductVariant::create([
        'company_id' => $this->company->id, 'branch_id' => $this->branchA->id,
        'product_id' => $product->id, 'sku' => 'SR-SKU', 'is_default' => true,
    ]);

    Stock::create(['company_id' => $this->company->id, 'branch_id' => $this->branchA->id, 'product_variant_id' => $variant->id, 'warehouse_id' => $whA->id, 'quantity' => 10]);
    Stock::create(['company_id' => $this->company->id, 'branch_id' => $this->branchB->id, 'product_variant_id' => $variant->id, 'warehouse_id' => $whB->id, 'quantity' => 5]);

    TenantService::reset();
    TenantService::setCompanyId($this->company->id);
});

afterEach(fn () => TenantService::reset());

it('returns only the selected branch stock rows in the reconciliation report', function () {
    $response = $this->withHeader('X-Branch-Id', (string) $this->branchA->id)
        ->getJson('/api/admin/inventory/stock-reconciliation?only_mismatch=0');

    $response->assertOk();
    $rows = $response->json('data');

    // Branch A's stock quantity is 10; branch B's is 5 and must not appear.
    expect($rows)->toHaveCount(1)
        ->and((float) $rows[0]['stock_quantity'])->toBe(10.0);
});

it('an admin with no branch selected sees every branch in the report', function () {
    $response = $this->getJson('/api/admin/inventory/stock-reconciliation?only_mismatch=0');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});
