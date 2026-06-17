<?php

use App\Models\Bom;
use App\Models\User;
use App\Models\Party;
use App\Models\Stock;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\TaxGroup;
use App\Enums\StatusEnum;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Jobs\CheckLowStockJob;
use App\Models\ProductVariant;
use App\Models\CustomerAdvance;
use App\Models\Role;
use App\Models\ProductionOrder;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use App\Models\ProductionOrderConsumption;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function p1secWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

function p1secMakeCompany(string $suffix): array
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
        'code' => "P1S-{$suffix}",
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $admin = User::create([
        'company_id' => $company->id,
        'name' => "Admin {$suffix}",
        'email' => "p1s-{$suffix}-".uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    return [$company, $admin, $fy];
}

beforeEach(function () {
    p1secWarmCache();
    TenantService::reset();
});

// ─────────────────────────────────────────────────────────────────────────────
// C-02: UserController cross-company isolation
// ─────────────────────────────────────────────────────────────────────────────

it('blocks viewing a user from another company', function () {
    [$companyA, $adminA] = p1secMakeCompany('UA');
    [$companyB] = p1secMakeCompany('UB');

    $userB = User::create([
        'company_id' => $companyB->id,
        'name' => 'Foreign User',
        'email' => 'fuser-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::USER,
    ]);

    Sanctum::actingAs($adminA, ['*'], 'admin');

    $this->getJson("/api/admin/user/{$userB->id}")
        ->assertForbidden();
});

it('blocks updating a user from another company', function () {
    [$companyA, $adminA] = p1secMakeCompany('UC');
    [$companyB] = p1secMakeCompany('UD');

    $userB = User::create([
        'company_id' => $companyB->id,
        'name' => 'Foreign User 2',
        'email' => 'fuser2-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::USER,
    ]);

    // A role is required by UpdateUserRequest; create one scoped to company A
    TenantService::setCompanyId($companyA->id);
    $roleA = Role::create(['company_id' => $companyA->id, 'name' => 'Editor', 'permissions' => []]);
    TenantService::reset();

    Sanctum::actingAs($adminA, ['*'], 'admin');

    // Company check runs after form validation — provide valid fields to reach the ownership check
    $this->putJson("/api/admin/user/{$userB->id}", [
        'name' => 'Hacked',
        'email' => $userB->email,
        'roles' => [$roleA->id],
    ])->assertForbidden();
});

it('blocks deleting a user from another company', function () {
    [$companyA, $adminA] = p1secMakeCompany('UE');
    [$companyB] = p1secMakeCompany('UF');

    $userB = User::create([
        'company_id' => $companyB->id,
        'name' => 'Foreign User 3',
        'email' => 'fuser3-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::USER,
    ]);

    Sanctum::actingAs($adminA, ['*'], 'admin');

    $this->deleteJson("/api/admin/user/{$userB->id}")
        ->assertForbidden();
});

it('blocks toggling status of a user from another company', function () {
    [$companyA, $adminA] = p1secMakeCompany('UG');
    [$companyB] = p1secMakeCompany('UH');

    $userB = User::create([
        'company_id' => $companyB->id,
        'name' => 'Foreign User 4',
        'email' => 'fuser4-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::USER,
    ]);

    Sanctum::actingAs($adminA, ['*'], 'admin');

    $this->putJson("/api/admin/user/{$userB->id}/update-status")
        ->assertForbidden();
});

it('allows viewing an own-company user', function () {
    [$companyA, $adminA] = p1secMakeCompany('UI');

    $ownUser = User::create([
        'company_id' => $companyA->id,
        'name' => 'Own Staff',
        'email' => 'ownstaff-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::USER,
    ]);

    Sanctum::actingAs($adminA, ['*'], 'admin');

    $this->getJson("/api/admin/user/{$ownUser->id}")
        ->assertSuccessful();
});

// ─────────────────────────────────────────────────────────────────────────────
// C-03: CustomerAdvance cross-company invoice application
// ─────────────────────────────────────────────────────────────────────────────

it('blocks applying an advance to an invoice from another company', function () {
    [$companyA, $adminA, $fyA] = p1secMakeCompany('CA');
    [$companyB, , $fyB] = p1secMakeCompany('CB');

    $partyA = Party::create(['company_id' => $companyA->id, 'name' => 'PartyA', 'code' => 'PA-'.uniqid(), 'type' => 'customer']);
    $partyB = Party::create(['company_id' => $companyB->id, 'name' => 'PartyB', 'code' => 'PB-'.uniqid(), 'type' => 'customer']);
    $cashA = Account::create(['company_id' => $companyA->id, 'name' => 'Cash A', 'code' => 'CSHA-'.uniqid()]);

    TenantService::setCompanyId($companyA->id);
    $advance = CustomerAdvance::create([
        'company_id' => $companyA->id,
        'fiscal_year_id' => $fyA->id,
        'party_id' => $partyA->id,
        'advance_no' => 'ADV-SEC-001',
        'advance_date' => '2026-01-01',
        'amount' => 1000,
        'applied_amount' => 0,
        'payment_method' => 'cash',
        'account_id' => $cashA->id,
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $adminA->id,
    ]);

    // Explicit company_id prevents MultiTenant creating hook from overriding
    $invoiceB = Invoice::create([
        'company_id' => $companyB->id,
        'fiscal_year_id' => $fyB->id,
        'party_id' => $partyB->id,
        'invoice_no' => 'INV-B-SEC-001',
        'invoice_date' => '2026-01-01',
        'total_amount' => 500,
        'paid_amount' => 0,
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $adminA->id,
    ]);

    TenantService::reset();
    Sanctum::actingAs($adminA, ['*'], 'admin');

    // Middleware sets company to A; Invoice::findOrFail scoped to A → 404 for B's invoice
    $this->postJson("/api/admin/customer-advance/{$advance->id}/apply", [
        'invoice_id' => $invoiceB->id,
        'amount' => 100,
    ])->assertNotFound();
});

// ─────────────────────────────────────────────────────────────────────────────
// H-05: TaxGroup cross-company isolation in tax calculation
// ─────────────────────────────────────────────────────────────────────────────

it('blocks calculating tax using a tax group from another company', function () {
    [$companyA, $adminA] = p1secMakeCompany('TA');
    [$companyB] = p1secMakeCompany('TB');

    // Explicit company_id bypasses scope stamping from MultiTenant creating hook
    $taxGroupB = TaxGroup::create([
        'company_id' => $companyB->id,
        'name' => 'VAT 13%',
        'is_active' => true,
    ]);

    Sanctum::actingAs($adminA, ['*'], 'admin');

    $this->postJson('/api/admin/tax/calculate', [
        'base_amount' => 100,
        'tax_group_id' => $taxGroupB->id,
    ])->assertNotFound();
});

it('allows calculating tax using an own-company tax group', function () {
    [$companyA, $adminA] = p1secMakeCompany('TC');

    TenantService::setCompanyId($companyA->id);
    $taxGroupA = TaxGroup::create([
        'company_id' => $companyA->id,
        'name' => 'Own VAT',
        'is_active' => true,
    ]);
    TenantService::reset();

    Sanctum::actingAs($adminA, ['*'], 'admin');

    $this->postJson('/api/admin/tax/calculate', [
        'base_amount' => 100,
        'tax_group_id' => $taxGroupA->id,
    ])->assertSuccessful();
});

// ─────────────────────────────────────────────────────────────────────────────
// H-01: ProductionOrder consumption IDOR
// ─────────────────────────────────────────────────────────────────────────────

it('blocks completing a production order using consumption IDs from a different order', function () {
    [$company, $admin, $fy] = p1secMakeCompany('PO');

    TenantService::setCompanyId($company->id);

    $warehouse = Warehouse::create(['company_id' => $company->id, 'name' => 'WH PO', 'code' => 'WHPO-'.uniqid()]);
    $product = Product::create(['company_id' => $company->id, 'name' => 'Output', 'code' => 'OPD-'.uniqid()]);
    $variant = ProductVariant::create(['company_id' => $company->id, 'product_id' => $product->id, 'sku' => 'OSK-'.uniqid(), 'is_default' => true]);

    $bomA = Bom::create(['company_id' => $company->id, 'product_variant_id' => $variant->id, 'name' => 'BOM-A', 'output_qty' => 1]);
    $bomB = Bom::create(['company_id' => $company->id, 'product_variant_id' => $variant->id, 'name' => 'BOM-B', 'output_qty' => 1]);

    $orderA = ProductionOrder::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $fy->id,
        'bom_id' => $bomA->id,
        'warehouse_id' => $warehouse->id,
        'order_no' => 'PO-A-001',
        'planned_qty' => 10,
        'status' => 'in_progress',
    ]);

    $orderB = ProductionOrder::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $fy->id,
        'bom_id' => $bomB->id,
        'warehouse_id' => $warehouse->id,
        'order_no' => 'PO-B-001',
        'planned_qty' => 5,
        'status' => 'in_progress',
    ]);

    $consumptionB = ProductionOrderConsumption::create([
        'production_order_id' => $orderB->id,
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'required_qty' => 2,
    ]);

    TenantService::reset();
    Sanctum::actingAs($admin, ['*'], 'admin');

    $this->postJson("/api/admin/production-order/{$orderA->id}/complete", [
        'produced_qty' => 5,
        'consumptions' => [['id' => $consumptionB->id, 'consumed_qty' => 1]],
    ])->assertForbidden();
});

it('allows completing a production order with its own consumption IDs', function () {
    [$company, $admin, $fy] = p1secMakeCompany('PO2');

    TenantService::setCompanyId($company->id);

    $warehouse = Warehouse::create(['company_id' => $company->id, 'name' => 'WH PO2', 'code' => 'WHPO2-'.uniqid()]);
    $product = Product::create(['company_id' => $company->id, 'name' => 'Output2', 'code' => 'OPD2-'.uniqid()]);
    $variant = ProductVariant::create(['company_id' => $company->id, 'product_id' => $product->id, 'sku' => 'OSK2-'.uniqid(), 'is_default' => true]);
    $bom = Bom::create(['company_id' => $company->id, 'product_variant_id' => $variant->id, 'name' => 'BOM-C', 'output_qty' => 1]);

    $order = ProductionOrder::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $fy->id,
        'bom_id' => $bom->id,
        'warehouse_id' => $warehouse->id,
        'order_no' => 'PO-C-001',
        'planned_qty' => 10,
        'status' => 'in_progress',
    ]);

    $ownConsumption = ProductionOrderConsumption::create([
        'production_order_id' => $order->id,
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'required_qty' => 2,
    ]);

    TenantService::reset();
    Sanctum::actingAs($admin, ['*'], 'admin');

    $this->postJson("/api/admin/production-order/{$order->id}/complete", [
        'produced_qty' => 5,
        'consumptions' => [['id' => $ownConsumption->id, 'consumed_qty' => 1]],
    ])->assertSuccessful();
});

// ─────────────────────────────────────────────────────────────────────────────
// C-04: CheckLowStockJob — tenant context is set and reset correctly
// ─────────────────────────────────────────────────────────────────────────────

it('resets TenantService to null after CheckLowStockJob completes', function () {
    [$company] = p1secMakeCompany('LS');

    TenantService::setCompanyId($company->id);

    $warehouse = Warehouse::create(['company_id' => $company->id, 'name' => 'WH LS', 'code' => 'WHLS-'.uniqid()]);
    $product = Product::create(['company_id' => $company->id, 'name' => 'Low Stock P', 'code' => 'LSP-'.uniqid()]);
    $variant = ProductVariant::create(['company_id' => $company->id, 'product_id' => $product->id, 'sku' => 'LSSK-'.uniqid(), 'is_default' => true]);

    $stock = Stock::create([
        'company_id' => $company->id,
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 0,
    ]);

    // Simulate a dirty static context left over from a previous job on the same worker
    TenantService::setCompanyId(999_999);

    $job = new CheckLowStockJob($stock);
    $job->handle();

    expect(TenantService::companyId())->toBeNull();
});

it('does not notify users from a different company when stock is low', function () {
    [$companyA] = p1secMakeCompany('LSA');
    [$companyB] = p1secMakeCompany('LSB');

    TenantService::setCompanyId($companyA->id);

    $warehouse = Warehouse::create(['company_id' => $companyA->id, 'name' => 'WH LSA', 'code' => 'WHLSA-'.uniqid()]);
    $product = Product::create([
        'company_id' => $companyA->id,
        'name' => 'Low Stock PA',
        'code' => 'LSPA-'.uniqid(),
        'min_stock_level' => 5,
    ]);
    $variant = ProductVariant::create([
        'company_id' => $companyA->id,
        'product_id' => $product->id,
        'sku' => 'LSSKA-'.uniqid(),
        'is_default' => true,
    ]);
    $stock = Stock::create([
        'company_id' => $companyA->id,
        'product_variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 1,
    ]);

    $userB = User::create([
        'company_id' => $companyB->id,
        'name' => 'Cross-company user',
        'email' => 'xco-'.uniqid().'@example.com',
        'password' => bcrypt('p'),
        'user_type' => UserTypeEnum::USER,
    ]);

    TenantService::reset();

    $job = new CheckLowStockJob($stock);
    $job->handle();

    expect($userB->fresh()->unreadNotifications()->count())->toBe(0);
    expect(TenantService::companyId())->toBeNull();
});
