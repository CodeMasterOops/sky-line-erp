<?php

use App\Models\Bill;
use App\Models\User;
use App\Models\Batch;
use App\Models\Party;
use App\Models\Stock;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Product;
use App\Models\BillItem;
use App\Enums\StatusEnum;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\StockTransfer;
use App\Enums\ProductTypeEnum;
use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use App\Services\TenantService;
use App\Enums\StockDirectionEnum;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use App\Services\Inventory\InventoryCostCalculator;

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
        'company_name' => 'Phase1 Test Co',
        'code' => 'P1TC',
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
        'email' => 'p1-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Main WH',
        'code' => 'WH1',
    ]);

    $this->warehouseB = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Secondary WH',
        'code' => 'WH2',
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Test Widget',
        'code' => 'WIDGET-P1',
        'product_type' => ProductTypeEnum::PRODUCT,
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'SKU-P1-1',
        'is_default' => true,
    ]);

    $this->serviceProduct = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Consulting',
        'code' => 'SVC-P1',
        'product_type' => ProductTypeEnum::SERVICE,
    ]);

    $this->serviceVariant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->serviceProduct->id,
        'sku' => 'SKU-SVC-1',
        'is_default' => true,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
    TenantService::setBranchId($this->branch->id);
});

// ─── BUG-001: Approved stock adjustment cannot be deleted ────────────────────

it('blocks deletion of an approved stock adjustment', function () {
    $adjustment = StockAdjustment::create([
        'company_id' => $this->company->id,
        'warehouse_id' => $this->warehouse->id,
        'date' => now()->toDateString(),
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    $this->deleteJson("/api/admin/stock-adjustment/{$adjustment->id}")
        ->assertUnprocessable();

    $this->assertDatabaseHas('stock_adjustments', ['id' => $adjustment->id, 'deleted_at' => null]);
});

it('allows deletion of a draft stock adjustment', function () {
    $adjustment = StockAdjustment::create([
        'company_id' => $this->company->id,
        'warehouse_id' => $this->warehouse->id,
        'date' => now()->toDateString(),
        'status' => StatusEnum::DRAFT,
        'create_user_id' => $this->user->id,
    ]);

    $this->deleteJson("/api/admin/stock-adjustment/{$adjustment->id}")
        ->assertOk();

    $this->assertSoftDeleted('stock_adjustments', ['id' => $adjustment->id]);
});

// ─── BUG-002: Approved stock transfer cannot be deleted ──────────────────────

it('blocks deletion of an approved stock transfer', function () {
    $transfer = StockTransfer::create([
        'company_id' => $this->company->id,
        'from_warehouse_id' => $this->warehouse->id,
        'to_warehouse_id' => $this->warehouseB->id,
        'date' => now()->toDateString(),
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    $this->deleteJson("/api/admin/stock-transfer/{$transfer->id}")
        ->assertUnprocessable();

    $this->assertDatabaseHas('stock_transfers', ['id' => $transfer->id, 'deleted_at' => null]);
});

it('allows deletion of a draft stock transfer', function () {
    $transfer = StockTransfer::create([
        'company_id' => $this->company->id,
        'from_warehouse_id' => $this->warehouse->id,
        'to_warehouse_id' => $this->warehouseB->id,
        'date' => now()->toDateString(),
        'status' => StatusEnum::DRAFT,
        'create_user_id' => $this->user->id,
    ]);

    $this->deleteJson("/api/admin/stock-transfer/{$transfer->id}")
        ->assertOk();

    $this->assertSoftDeleted('stock_transfers', ['id' => $transfer->id]);
});

// ─── BUG-006: defaultVariant() returns the is_default variant ───────────────

it('defaultVariant returns the variant marked as default not the first inserted', function () {
    $extra = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'SKU-P1-2',
        'is_default' => false,
    ]);

    $default = $this->product->fresh()->defaultVariant;

    expect($default)->not->toBeNull();
    expect($default->id)->toBe($this->variant->id);
});

// ─── BUG-009: Fractional quantity cost calculation ───────────────────────────

it('calculates unit cost correctly for fractional quantities', function () {
    $bill = Bill::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'bill_no' => 'BILL-FRAC-1',
        'bill_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $item = BillItem::create([
        'bill_id' => $bill->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 1.5,
        'rate' => 100,
        'discount_amount' => 0,
    ]);

    $cost = InventoryCostCalculator::unitCostFromBillItem($item);

    // 1.5 * 100 / 1.5 = 100.0 (integer cast would give 150.0 by dividing by 1)
    expect($cost)->toBe(100.0);
});

// ─── VAL-001: Service products rejected in stock adjustment ──────────────────

it('rejects service-type product variant in stock adjustment', function () {
    $payload = [
        'date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'status' => StatusEnum::DRAFT->value,
        'items' => [[
            'product_variant_id' => $this->serviceVariant->id,
            'direction' => StockDirectionEnum::IN->value,
            'quantity' => 5,
            'unit_cost' => 10,
        ]],
    ];

    $this->postJson('/api/admin/stock-adjustment', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.product_variant_id']);
});

it('accepts physical product variant in stock adjustment', function () {
    $payload = [
        'date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'status' => StatusEnum::DRAFT->value,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'direction' => StockDirectionEnum::IN->value,
            'quantity' => 5,
            'unit_cost' => 10,
        ]],
    ];

    $this->postJson('/api/admin/stock-adjustment', $payload)
        ->assertCreated();
});

// ─── BL-001: Product with stock cannot be deleted ────────────────────────────

it('blocks deletion of product that has stock on hand', function () {
    Stock::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 10,
    ]);

    $this->deleteJson("/api/admin/product/{$this->product->id}")
        ->assertUnprocessable();

    $this->assertDatabaseHas('products', ['id' => $this->product->id, 'deleted_at' => null]);
});

it('allows deletion of product with zero stock', function () {
    Stock::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 0,
    ]);

    $this->deleteJson("/api/admin/product/{$this->product->id}")
        ->assertOk();

    $this->assertSoftDeleted('products', ['id' => $this->product->id]);
});

// ─── SEC-001: GRN billableItems party_id cross-tenant scoping ────────────────

it('rejects a party_id belonging to a different company in GRN billableItems', function () {
    $otherCompany = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Other Co',
        'code' => 'OTH1',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $otherParty = Party::create([
        'company_id' => $otherCompany->id,
        'name' => 'Foreign Supplier',
        'code' => 'FOR-SUP-01',
        'type' => 'supplier',
    ]);

    $this->getJson("/api/admin/grn/billable-items?party_id={$otherParty->id}")
        ->assertUnprocessable();
});

// ─── BUG-003: FEFO scope null sort order ─────────────────────────────────────

it('fefo scope returns batches with expiry dates before null-expiry batches', function () {
    $batchNoExpiry = Batch::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'batch_no' => 'B-NO-EXPIRY',
        'expiry_date' => null,
        'initial_qty' => 5,
        'remaining_qty' => 5,
        'status' => 'active',
    ]);

    $batchWithExpiry = Batch::create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'batch_no' => 'B-EXPIRE-SOON',
        'expiry_date' => now()->addDays(10)->toDateString(),
        'initial_qty' => 5,
        'remaining_qty' => 5,
        'status' => 'active',
    ]);

    $ordered = Batch::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->fefo()
        ->pluck('id')
        ->all();

    // Batch with expiry date must come before the null-expiry batch
    expect(array_search($batchWithExpiry->id, $ordered))
        ->toBeLessThan(array_search($batchNoExpiry->id, $ordered));
});
