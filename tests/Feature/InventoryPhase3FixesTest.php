<?php

use App\Models\User;
use App\Models\Stock;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Product;
use App\Enums\StatusEnum;
use App\Models\Warehouse;
use App\Models\CreditNote;
use App\Models\FiscalYear;
use App\Models\StockLayer;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Enums\ChangeTypeEnum;
use App\Models\StockMovement;
use App\Enums\ProductTypeEnum;
use App\Models\ProductVariant;
use App\Services\TenantService;
use App\Enums\StockDirectionEnum;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use App\Services\Inventory\InventoryDocumentReversalService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Phase3 Test Co',
        'code' => 'P3TC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'name' => 'Main Branch',
        'code' => 'BR1',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Admin',
        'email' => 'p3-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Main WH',
        'code' => 'WH1',
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Phase3 Widget',
        'code' => 'WIDGET-P3',
        'product_type' => ProductTypeEnum::PRODUCT,
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'SKU-P3-A',
        'is_default' => true,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
    TenantService::setBranchId($this->branch->id);
});

// ─── BUG-012: reverseApprovedCreditNote uses SALE not ADJUSTMENT_OUT ─────────

it('reverseApprovedCreditNote creates a SALE counter movement not ADJUSTMENT_OUT', function () {
    Stock::withoutGlobalScopes()->create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 5,
    ]);
    StockLayer::withoutGlobalScopes()->create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'qty_remaining' => 5,
        'unit_cost' => 100.0,
        'received_at' => now()->subDay(),
    ]);

    $creditNote = CreditNote::withoutGlobalScopes()->create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'credit_note_no' => 'CN-P3-001',
        'credit_note_date' => '2026-06-15',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
    ]);

    StockMovement::withoutGlobalScopes()->create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'reference_type' => $creditNote->getMorphClass(),
        'reference_id' => $creditNote->id,
        'type' => ChangeTypeEnum::RETURN_IN,
        'direction' => StockDirectionEnum::IN,
        'quantity' => 2,
        'unit_cost' => 100.0,
        'total_cost' => 200.0,
        'user_id' => $this->user->id,
    ]);

    /** @var InventoryDocumentReversalService $service */
    $service = app(InventoryDocumentReversalService::class);
    $service->reverseApprovedCreditNote($creditNote, $this->company, $this->user->id);

    $reversalMovement = StockMovement::withoutGlobalScopes()
        ->where('reference_type', $creditNote->getMorphClass())
        ->where('reference_id', $creditNote->id)
        ->where('direction', StockDirectionEnum::OUT->value)
        ->first();

    expect($reversalMovement)->not->toBeNull()
        ->and($reversalMovement->type)->toBe(ChangeTypeEnum::SALE)
        ->and((int) $reversalMovement->quantity)->toBe(2);
});

it('reverseApprovedCreditNote does NOT create an ADJUSTMENT_OUT movement', function () {
    Stock::withoutGlobalScopes()->create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 3,
    ]);
    StockLayer::withoutGlobalScopes()->create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'qty_remaining' => 3,
        'unit_cost' => 50.0,
        'received_at' => now()->subDay(),
    ]);

    $creditNote = CreditNote::withoutGlobalScopes()->create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'credit_note_no' => 'CN-P3-002',
        'credit_note_date' => '2026-06-15',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
    ]);

    StockMovement::withoutGlobalScopes()->create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'reference_type' => $creditNote->getMorphClass(),
        'reference_id' => $creditNote->id,
        'type' => ChangeTypeEnum::RETURN_IN,
        'direction' => StockDirectionEnum::IN,
        'quantity' => 1,
        'unit_cost' => 50.0,
        'total_cost' => 50.0,
        'user_id' => $this->user->id,
    ]);

    /** @var InventoryDocumentReversalService $service */
    $service = app(InventoryDocumentReversalService::class);
    $service->reverseApprovedCreditNote($creditNote, $this->company, $this->user->id);

    $adjustmentOut = StockMovement::withoutGlobalScopes()
        ->where('reference_type', $creditNote->getMorphClass())
        ->where('reference_id', $creditNote->id)
        ->where('type', ChangeTypeEnum::ADJUSTMENT_OUT->value)
        ->count();

    expect($adjustmentOut)->toBe(0);
});

// ─── BL-007: applyApprovalEffects no longer calls auth() internally ──────────

it('stock adjustment approval via API endpoint works correctly', function () {
    $id = $this->postJson('/api/admin/stock-adjustment', [
        'date' => '2026-06-15',
        'warehouse_id' => $this->warehouse->id,
        'status' => StatusEnum::DRAFT->value,
        'items' => [
            ['product_variant_id' => $this->variant->id, 'direction' => StockDirectionEnum::IN->value, 'quantity' => 5, 'unit_cost' => 20],
        ],
    ])->assertCreated()->json('data.id');

    $this->postJson("/api/admin/stock-adjustment/{$id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', StatusEnum::APPROVED->value);

    $movement = StockMovement::withoutGlobalScopes()
        ->where('type', ChangeTypeEnum::ADJUSTMENT_IN->value)
        ->first();

    expect($movement)->not->toBeNull()
        ->and($movement->user_id)->toBe($this->user->id);
});

it('stock transfer approval via API endpoint works correctly', function () {
    Stock::withoutGlobalScopes()->create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 10,
    ]);
    StockLayer::withoutGlobalScopes()->create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'qty_remaining' => 10,
        'unit_cost' => 15.0,
        'received_at' => now()->subHour(),
    ]);

    $warehouseB = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Secondary WH',
        'code' => 'WH-B',
    ]);

    $id = $this->postJson('/api/admin/stock-transfer', [
        'date' => '2026-06-15',
        'from_warehouse_id' => $this->warehouse->id,
        'to_warehouse_id' => $warehouseB->id,
        'status' => StatusEnum::DRAFT->value,
        'items' => [
            ['product_variant_id' => $this->variant->id, 'quantity' => 3],
        ],
    ])->assertCreated()->json('data.id');

    $this->postJson("/api/admin/stock-transfer/{$id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', StatusEnum::APPROVED->value);
});

// ─── NEPAL-002: Fiscal year boundary validation on inventory documents ────────

it('stock adjustment rejects a date before the active fiscal year', function () {
    $this->postJson('/api/admin/stock-adjustment', [
        'date' => '2025-12-31',
        'warehouse_id' => $this->warehouse->id,
        'status' => StatusEnum::DRAFT->value,
        'items' => [
            ['product_variant_id' => $this->variant->id, 'direction' => StockDirectionEnum::IN->value, 'quantity' => 1, 'unit_cost' => 10],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['date']);
});

it('stock adjustment rejects a date after the active fiscal year', function () {
    $this->postJson('/api/admin/stock-adjustment', [
        'date' => '2027-01-01',
        'warehouse_id' => $this->warehouse->id,
        'status' => StatusEnum::DRAFT->value,
        'items' => [
            ['product_variant_id' => $this->variant->id, 'direction' => StockDirectionEnum::IN->value, 'quantity' => 1, 'unit_cost' => 10],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['date']);
});

it('stock adjustment accepts a date within the active fiscal year', function () {
    $this->postJson('/api/admin/stock-adjustment', [
        'date' => '2026-06-15',
        'warehouse_id' => $this->warehouse->id,
        'status' => StatusEnum::DRAFT->value,
        'items' => [
            ['product_variant_id' => $this->variant->id, 'direction' => StockDirectionEnum::IN->value, 'quantity' => 1, 'unit_cost' => 10],
        ],
    ])->assertCreated();
});

it('opening stock entry rejects a date outside the active fiscal year', function () {
    $this->postJson('/api/admin/opening-stock-entry', [
        'date' => '2027-01-01',
        'warehouse_id' => $this->warehouse->id,
        'status' => StatusEnum::DRAFT->value,
        'items' => [
            ['product_variant_id' => $this->variant->id, 'quantity' => 5, 'unit_cost' => 10],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['date']);
});

it('opening stock entry accepts a date within the active fiscal year', function () {
    $this->postJson('/api/admin/opening-stock-entry', [
        'date' => '2026-03-01',
        'warehouse_id' => $this->warehouse->id,
        'status' => StatusEnum::DRAFT->value,
        'items' => [
            ['product_variant_id' => $this->variant->id, 'quantity' => 5, 'unit_cost' => 10],
        ],
    ])->assertCreated();
});

it('stock transfer rejects a date outside the active fiscal year', function () {
    $warehouseB = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Secondary WH',
        'code' => 'WH-B2',
    ]);

    $this->postJson('/api/admin/stock-transfer', [
        'date' => '2024-12-01',
        'from_warehouse_id' => $this->warehouse->id,
        'to_warehouse_id' => $warehouseB->id,
        'status' => StatusEnum::DRAFT->value,
        'items' => [
            ['product_variant_id' => $this->variant->id, 'quantity' => 1],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['date']);
});

it('stock transfer accepts a date within the active fiscal year', function () {
    $warehouseB = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Secondary WH',
        'code' => 'WH-B3',
    ]);

    $this->postJson('/api/admin/stock-transfer', [
        'date' => '2026-09-10',
        'from_warehouse_id' => $this->warehouse->id,
        'to_warehouse_id' => $warehouseB->id,
        'status' => StatusEnum::DRAFT->value,
        'items' => [
            ['product_variant_id' => $this->variant->id, 'quantity' => 1],
        ],
    ])->assertCreated();
});
