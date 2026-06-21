<?php

use App\Models\Bom;
use App\Models\User;
use App\Models\BomItem;
use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\BomOperation;
use Laravel\Sanctum\Sanctum;
use App\Enums\ChangeTypeEnum;
use App\Models\ProductVariant;
use Illuminate\Support\Carbon;
use App\Models\StockAdjustment;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use App\Services\Inventory\InventoryLayerReceiptService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function opTimeWarmCache(): void
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
    opTimeWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);
    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'OpTime Co', 'code' => 'OPT',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);
    $this->user = User::create([
        'company_id' => $this->company->id, 'name' => 'OpTime Tester',
        'email' => 'optime-'.uniqid().'@example.com',
        'password' => bcrypt('password'), 'user_type' => UserTypeEnum::ADMIN,
    ]);
    $this->warehouse = Warehouse::create(['company_id' => $this->company->id, 'name' => 'Factory', 'code' => 'FACT']);

    $rawProduct = Product::create(['company_id' => $this->company->id, 'name' => 'Bar', 'code' => 'BAR']);
    $this->rawVariant = ProductVariant::create([
        'company_id' => $this->company->id, 'product_id' => $rawProduct->id,
        'sku' => 'BAR-1', 'is_default' => true, 'purchase_price' => 5.0,
    ]);
    $fgProduct = Product::create(['company_id' => $this->company->id, 'name' => 'Frame', 'code' => 'FRAME']);
    $this->fgVariant = ProductVariant::create([
        'company_id' => $this->company->id, 'product_id' => $fgProduct->id,
        'sku' => 'FRAME-1', 'is_default' => true,
    ]);

    $this->bom = Bom::create([
        'company_id' => $this->company->id, 'product_variant_id' => $this->fgVariant->id,
        'name' => 'Frame BOM', 'version' => '1', 'output_qty' => 1, 'is_active' => true, 'is_default' => true,
    ]);
    BomItem::create([
        'bom_id' => $this->bom->id, 'product_variant_id' => $this->rawVariant->id,
        'quantity' => 1, 'item_type' => 'material', 'wastage_pct' => 0,
    ]);
    // Standard time for the single operation is 20 minutes
    BomOperation::create([
        'bom_id' => $this->bom->id, 'company_id' => $this->company->id,
        'sequence' => 1, 'name' => 'Cutting', 'work_center' => 'Saw', 'duration_minutes' => 20,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);

    // Seed raw stock so material reservation on order creation succeeds.
    $adj = StockAdjustment::create([
        'company_id' => $this->company->id, 'reference_no' => 'SEED-'.uniqid(),
        'date' => now()->toDateString(), 'warehouse_id' => $this->warehouse->id,
        'create_user_id' => $this->user->id, 'status' => 'approved',
    ]);
    DB::transaction(fn () => app(InventoryLayerReceiptService::class)->receive(
        $this->company, $adj, $this->rawVariant->id, $this->warehouse->id,
        10, 5.0, ChangeTypeEnum::PURCHASE, $this->user->id, null,
    ));
});

it('exposes standard and actual operation minutes after completing an operation', function () {
    $res = $this->postJson('/api/admin/production-order', [
        'bom_id' => $this->bom->id, 'warehouse_id' => $this->warehouse->id, 'planned_qty' => 1,
    ]);
    $orderId = $res->json('data.id');
    $opId = $res->json('data.operations.0.id');

    $this->postJson("/api/admin/production-order/{$orderId}/start")->assertOk();

    Carbon::setTestNow('2026-06-21 09:00:00');
    $this->postJson("/api/admin/production-order/{$orderId}/operations/{$opId}/start")->assertOk();

    // 30 minutes of actual run time vs 20 standard
    Carbon::setTestNow('2026-06-21 09:30:00');
    $this->postJson("/api/admin/production-order/{$orderId}/operations/{$opId}/complete", [])->assertOk();
    Carbon::setTestNow();

    $show = $this->getJson("/api/admin/production-order/{$orderId}");
    $show->assertSuccessful();

    $op = collect($show->json('data.operations'))->firstWhere('id', $opId);
    expect($op['standard_minutes'])->toBe(20);
    expect($op['actual_minutes'])->toBe(30);
});

it('returns null actual minutes for an operation that has not been completed', function () {
    $res = $this->postJson('/api/admin/production-order', [
        'bom_id' => $this->bom->id, 'warehouse_id' => $this->warehouse->id, 'planned_qty' => 1,
    ]);
    $orderId = $res->json('data.id');
    $opId = $res->json('data.operations.0.id');

    $show = $this->getJson("/api/admin/production-order/{$orderId}");
    $op = collect($show->json('data.operations'))->firstWhere('id', $opId);

    expect($op['standard_minutes'])->toBe(20);
    expect($op['actual_minutes'])->toBeNull();
});
