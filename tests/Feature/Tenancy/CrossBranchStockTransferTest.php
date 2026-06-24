<?php

use App\Models\Role;
use App\Models\User;
use App\Models\Stock;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\BranchUser;
use App\Models\FiscalYear;
use App\Models\StockLayer;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function xbtWarmCache(): void
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
    xbtWarmCache();

    $this->fy = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);
    $this->company = Company::create([
        'fiscal_year_id' => $this->fy->id, 'company_name' => 'Co', 'code' => 'XBT-CO',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);
    TenantService::setCompanyId($this->company->id);

    $this->branchA = Branch::create(['company_id' => $this->company->id, 'name' => 'A', 'code' => 'XBT-A', 'is_head_office' => true, 'is_active' => true]);
    $this->branchB = Branch::create(['company_id' => $this->company->id, 'name' => 'B', 'code' => 'XBT-B', 'is_active' => true]);

    $this->whA = Warehouse::create(['company_id' => $this->company->id, 'branch_id' => $this->branchA->id, 'name' => 'WH-A', 'code' => 'XWA']);
    $this->whB = Warehouse::create(['company_id' => $this->company->id, 'branch_id' => $this->branchB->id, 'name' => 'WH-B', 'code' => 'XWB']);

    $product = Product::create(['company_id' => $this->company->id, 'branch_id' => $this->branchA->id, 'name' => 'W', 'code' => 'XBT-W']);
    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id, 'branch_id' => $this->branchA->id,
        'product_id' => $product->id, 'sku' => 'XBT-SKU', 'is_default' => true,
    ]);

    // Opening stock at warehouse A (branch A). branch_id derives from the warehouse.
    Stock::create(['company_id' => $this->company->id, 'product_variant_id' => $this->variant->id, 'warehouse_id' => $this->whA->id, 'quantity' => 100]);
    StockLayer::create([
        'company_id' => $this->company->id, 'product_variant_id' => $this->variant->id, 'warehouse_id' => $this->whA->id,
        'quantity' => 100, 'qty_remaining' => 100, 'unit_cost' => 10.00, 'received_at' => now(),
    ]);

    $this->admin = User::create([
        'company_id' => $this->company->id, 'name' => 'Admin', 'email' => 'xbt-'.uniqid().'@t.test',
        'password' => bcrypt('x'), 'user_type' => UserTypeEnum::ADMIN,
    ]);
});

afterEach(fn () => TenantService::reset());

it('lands transferred stock in the destination branch on a cross-branch transfer', function () {
    Sanctum::actingAs($this->admin, ['*'], 'admin');

    $this->postJson('/api/admin/stock-transfer', [
        'date' => '2026-06-01',
        'from_warehouse_id' => $this->whA->id,
        'to_warehouse_id' => $this->whB->id,
        'status' => 'approved',
        'items' => [['product_variant_id' => $this->variant->id, 'quantity' => 30]],
    ])->assertCreated();

    $destStock = Stock::withoutGlobalScopes()
        ->where('warehouse_id', $this->whB->id)->where('product_variant_id', $this->variant->id)->first();
    expect($destStock)->not->toBeNull()
        ->and((float) $destStock->quantity)->toBe(30.0)
        ->and((int) $destStock->branch_id)->toBe($this->branchB->id);

    $srcStock = Stock::withoutGlobalScopes()
        ->where('warehouse_id', $this->whA->id)->where('product_variant_id', $this->variant->id)->first();
    expect((float) $srcStock->quantity)->toBe(70.0)
        ->and((int) $srcStock->branch_id)->toBe($this->branchA->id);

    // The destination layer is also stamped to the destination branch.
    $destLayer = StockLayer::withoutGlobalScopes()->where('warehouse_id', $this->whB->id)->first();
    expect((int) $destLayer->branch_id)->toBe($this->branchB->id);
});

it('forbids a branch user from transferring into a branch they cannot access', function () {
    $user = User::create([
        'company_id' => $this->company->id, 'name' => 'BranchA User', 'email' => 'xbt-u-'.uniqid().'@t.test',
        'password' => bcrypt('x'), 'user_type' => UserTypeEnum::USER,
    ]);
    $role = Role::create(['company_id' => $this->company->id, 'name' => 'Warehouse', 'permissions' => ['create_stock_transfer']]);
    $user->roles()->attach($role);
    BranchUser::create(['company_id' => $this->company->id, 'branch_id' => $this->branchA->id, 'user_id' => $user->id, 'role_id' => $role->id, 'is_active' => true]);

    Sanctum::actingAs($user, ['*'], 'admin');

    // Destination warehouse B is in branch B, which this user cannot access.
    $this->postJson('/api/admin/stock-transfer', [
        'date' => '2026-06-01',
        'from_warehouse_id' => $this->whA->id,
        'to_warehouse_id' => $this->whB->id,
        'status' => 'draft',
        'items' => [['product_variant_id' => $this->variant->id, 'from_warehouse_id' => $this->whA->id, 'quantity' => 5]],
    ])->assertStatus(422);
});
