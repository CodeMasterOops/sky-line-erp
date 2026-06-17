<?php

use App\Models\Bom;
use App\Models\User;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Enums\StatusEnum;
use App\Models\Warehouse;
use App\Models\BranchUser;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\ProductVariant;
use App\Models\ProductionOrder;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use App\Http\Middleware\SetTenantContext;
use App\Models\ProductionOrderConsumption;
use Illuminate\Http\Response as HttpResponse;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function p3secWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
}

/**
 * Creates a minimal ProductionOrder for testing and returns [order, variantId, warehouseId].
 * Caller must set TenantService::setCompanyId() before calling this.
 */
function p3secMakeProductionOrder(Company $company, FiscalYear $fy, string $suffix): array
{
    $product = Product::create([
        'company_id' => $company->id,
        'name' => "Product {$suffix}",
        'code' => "P3P-{$suffix}-".uniqid(),
    ]);
    $variant = ProductVariant::create([
        'company_id' => $company->id,
        'product_id' => $product->id,
    ]);
    $bom = Bom::create([
        'company_id' => $company->id,
        'product_variant_id' => $variant->id,
        'name' => "BOM {$suffix}",
    ]);
    $warehouse = Warehouse::create([
        'company_id' => $company->id,
        'name' => "Warehouse {$suffix}",
        'code' => "WH-{$suffix}-".uniqid(),
    ]);
    $order = ProductionOrder::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $fy->id,
        'bom_id' => $bom->id,
        'warehouse_id' => $warehouse->id,
        'order_no' => "PO-{$suffix}-".uniqid(),
        'status' => 'draft',
        'planned_qty' => 1,
    ]);

    return [$order, $variant->id, $warehouse->id];
}

function p3secMakeCompany(string $suffix): array
{
    $fy = FiscalYear::create([
        'year_name' => "2026{$suffix}",
        'year_code' => "26{$suffix}",
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $company = Company::create([
        'fiscal_year_id' => $fy->id,
        'company_name' => "Company {$suffix}",
        'code' => "P3S-{$suffix}",
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $admin = User::create([
        'company_id' => $company->id,
        'name' => "Admin {$suffix}",
        'email' => "p3s-{$suffix}-".uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    return [$company, $admin, $fy];
}

beforeEach(function () {
    p3secWarmCache();
    TenantService::reset();
});

// ─────────────────────────────────────────────────────────────────────────────
// 3.2 — Architectural: every authenticated admin API route has SetTenantContext
// ─────────────────────────────────────────────────────────────────────────────

it('all authenticated admin API routes include SetTenantContext middleware', function () {
    // These routes are pre-auth or do not access tenant data.
    // Logout has no route name so we check by URI.
    $publicUris = [
        'api/admin/login',
        'api/admin/register',
        'api/admin/logout',
    ];

    $violations = [];

    foreach (Route::getRoutes() as $route) {
        $uri = $route->uri();

        // Only check admin API routes
        if (! str_starts_with($uri, 'api/admin')) {
            continue;
        }

        // Skip truly public routes (pre-auth)
        if (in_array($uri, $publicUris, true)) {
            continue;
        }

        $middleware = $route->gatherMiddleware();
        $hasSetTenantContext = collect($middleware)
            ->contains(fn ($m) => $m === SetTenantContext::class || $m === 'App\\Http\\Middleware\\SetTenantContext');

        if (! $hasSetTenantContext) {
            $violations[] = $uri;
        }
    }

    expect($violations)->toBeEmpty(
        'Routes missing SetTenantContext: '.implode(', ', $violations)
    );
});

// ─────────────────────────────────────────────────────────────────────────────
// 3.3 — BranchTenant: non-admin users scoped to accessible branches
// ─────────────────────────────────────────────────────────────────────────────

it('non-admin user without X-Branch-Id sees only their assigned branches data', function () {
    [$companyA, $adminA, $fyA] = p3secMakeCompany('P3BA');

    $branchA = Branch::create(['company_id' => $companyA->id, 'name' => 'Branch A', 'code' => 'BA-'.uniqid()]);
    $branchB = Branch::create(['company_id' => $companyA->id, 'name' => 'Branch B', 'code' => 'BB-'.uniqid()]);

    // Create a non-admin user assigned only to branch A
    $staffUser = User::create([
        'company_id' => $companyA->id,
        'name' => 'Staff',
        'email' => 'p3-staff-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::USER,
    ]);
    BranchUser::create([
        'company_id' => $companyA->id,
        'user_id' => $staffUser->id,
        'branch_id' => $branchA->id,
    ]);

    TenantService::setCompanyId($companyA->id);

    // Create invoices in each branch
    $invoiceA = Invoice::create([
        'company_id' => $companyA->id,
        'fiscal_year_id' => $fyA->id,
        'branch_id' => $branchA->id,
        'invoice_no' => 'INV-P3-A-'.uniqid(),
        'invoice_date' => now()->toDateString(),
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $adminA->id,
    ]);

    $invoiceB = Invoice::create([
        'company_id' => $companyA->id,
        'fiscal_year_id' => $fyA->id,
        'branch_id' => $branchB->id,
        'invoice_no' => 'INV-P3-B-'.uniqid(),
        'invoice_date' => now()->toDateString(),
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $adminA->id,
    ]);

    // Authenticate as the staff user so auth('admin')->user() resolves in BranchTenant scope
    $this->actingAs($staffUser, 'admin');

    // No X-Branch-Id — TenantService has company set but no branch.
    // BranchTenant scope must restrict to branchA only (staff's accessible branch).
    $ids = Invoice::all()->pluck('id');

    expect($ids)->toContain($invoiceA->id)
        ->and($ids)->not->toContain($invoiceB->id);
});

it('admin user without X-Branch-Id sees all branches data', function () {
    [$companyA, $adminA, $fyA] = p3secMakeCompany('P3BB');

    $branchA = Branch::create(['company_id' => $companyA->id, 'name' => 'Branch AA', 'code' => 'BAA-'.uniqid()]);
    $branchB = Branch::create(['company_id' => $companyA->id, 'name' => 'Branch BB', 'code' => 'BBB-'.uniqid()]);

    TenantService::setCompanyId($companyA->id);

    $invoiceA = Invoice::create([
        'company_id' => $companyA->id,
        'fiscal_year_id' => $fyA->id,
        'branch_id' => $branchA->id,
        'invoice_no' => 'INV-P3-AA-'.uniqid(),
        'invoice_date' => now()->toDateString(),
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $adminA->id,
    ]);

    $invoiceB = Invoice::create([
        'company_id' => $companyA->id,
        'fiscal_year_id' => $fyA->id,
        'branch_id' => $branchB->id,
        'invoice_no' => 'INV-P3-BB-'.uniqid(),
        'invoice_date' => now()->toDateString(),
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $adminA->id,
    ]);

    // Admin user — no X-Branch-Id. BranchTenant scope must not restrict admins.
    $this->actingAs($adminA, 'admin');

    $ids = Invoice::all()->pluck('id');

    expect($ids)->toContain($invoiceA->id)
        ->and($ids)->toContain($invoiceB->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// 3.4 — ProductionOrderConsumption has company_id + MultiTenant scope
// ─────────────────────────────────────────────────────────────────────────────

it('production order consumption is scoped to own company', function () {
    [$companyA, $adminA, $fyA] = p3secMakeCompany('P3CA');
    [$companyB, $adminB, $fyB] = p3secMakeCompany('P3CB');

    TenantService::setCompanyId($companyA->id);
    [$orderA, $variantIdA, $warehouseIdA] = p3secMakeProductionOrder($companyA, $fyA, 'A');
    $consumptionA = ProductionOrderConsumption::create([
        'company_id' => $companyA->id,
        'production_order_id' => $orderA->id,
        'product_variant_id' => $variantIdA,
        'warehouse_id' => $warehouseIdA,
        'required_qty' => 5,
        'consumed_qty' => 0,
    ]);
    TenantService::reset();

    TenantService::setCompanyId($companyB->id);
    [$orderB, $variantIdB, $warehouseIdB] = p3secMakeProductionOrder($companyB, $fyB, 'B');
    $consumptionB = ProductionOrderConsumption::create([
        'company_id' => $companyB->id,
        'production_order_id' => $orderB->id,
        'product_variant_id' => $variantIdB,
        'warehouse_id' => $warehouseIdB,
        'required_qty' => 3,
        'consumed_qty' => 0,
    ]);
    TenantService::reset();

    TenantService::setCompanyId($companyA->id);
    $visible = ProductionOrderConsumption::all()->pluck('id');
    TenantService::reset();

    expect($visible)->toContain($consumptionA->id)
        ->and($visible)->not->toContain($consumptionB->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// 3.5 — SetTenantContext::terminate() resets static tenant state
// ─────────────────────────────────────────────────────────────────────────────

it('SetTenantContext terminate clears the tenant state', function () {
    TenantService::setCompanyId(999);
    TenantService::setBranchId(888);

    $middleware = app(\App\Http\Middleware\SetTenantContext::class);
    $middleware->terminate(request(), new HttpResponse);

    expect(TenantService::companyId())->toBeNull()
        ->and(TenantService::branchId())->toBeNull();
});
