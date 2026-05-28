<?php

use App\Models\Unit;
use App\Models\User;
use App\Models\Stock;
use App\Models\Branch;
use App\Models\Account;
use App\Models\Company;
use App\Models\Journal;
use App\Models\Product;
use App\Enums\StatusEnum;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Models\StockLayer;
use App\Enums\UserTypeEnum;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use App\Enums\ChangeTypeEnum;
use App\Models\StockMovement;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Models\DataTransferJob;
use App\Models\ProductCategory;
use App\Services\TenantService;
use App\Models\OpeningStockEntry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use App\Enums\DataTransfer\DataTransferStatusEnum;
use App\Services\DataTransfer\ProductImportService;
use App\Services\Inventory\OpeningStockEntryService;
use App\Enums\DataTransfer\DataTransferDirectionEnum;
use App\Enums\DataTransfer\DataTransferEntityTypeEnum;
use App\Services\DataTransfer\ProductImportLookupCache;
use App\Services\DataTransfer\ProductImportRowValidator;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $tables[$table] = Schema::getColumnListing($table);
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
        'company_name' => 'Opening Stock Co',
        'code' => 'OSC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'name' => 'Main Branch',
        'code' => 'BR1',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Opening Stock Admin',
        'email' => 'ose-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Main',
        'code' => 'W1',
    ]);

    $this->category = ProductCategory::create([
        'company_id' => $this->company->id,
        'name' => 'General',
    ]);

    $this->unit = Unit::create([
        'company_id' => $this->company->id,
        'name' => 'Piece',
        'code' => 'PC',
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Widget',
        'code' => 'WIDGET-OSE',
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'SKU-OSE-1',
        'purchase_price' => 12.5,
        'is_default' => true,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
    TenantService::setBranchId($this->branch->id);
});

function openingStockPayload(object $test, array $overrides = []): array
{
    return array_merge([
        'reference_no' => 'OSE-001',
        'date' => '2026-01-15',
        'warehouse_id' => $test->warehouse->id,
        'remarks' => 'Go-live stock',
        'status' => 'draft',
        'items' => [
            [
                'product_variant_id' => $test->variant->id,
                'unit_id' => null,
                'quantity' => 10,
                'unit_cost' => 12.5,
            ],
        ],
    ], $overrides);
}

test('opening stock index includes product names from line items', function () {
    $this->postJson('/api/admin/opening-stock-entry', openingStockPayload($this))->assertCreated();

    $list = $this->getJson('/api/admin/opening-stock-entry');

    $list->assertSuccessful()
        ->assertJsonPath('data.0.product_names', 'Widget');
});

test('approving opening stock entry updates stock layers and movement type', function () {
    $response = $this->postJson('/api/admin/opening-stock-entry', openingStockPayload($this));

    $response->assertCreated();
    $entryId = $response->json('data.id');

    $approve = $this->postJson("/api/admin/opening-stock-entry/{$entryId}/approve");
    $approve->assertSuccessful();

    $stock = Stock::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('product_variant_id', $this->variant->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();

    expect($stock->quantity)->toBe(10);

    $movement = StockMovement::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('product_variant_id', $this->variant->id)
        ->first();

    expect($movement)->not->toBeNull()
        ->and($movement->type)->toBe(ChangeTypeEnum::OPENING_STOCK);

    $valuedQty = (int) StockLayer::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('product_variant_id', $this->variant->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->sum('qty_remaining');

    expect($valuedQty)->toBe(10);
});

test('opening stock approval rejects when variant already has stock in warehouse', function () {
    Stock::withoutGlobalScopes()->create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 5,
        'on_hold' => 0,
    ]);

    $entry = OpeningStockEntry::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'date' => '2026-01-15',
        'warehouse_id' => $this->warehouse->id,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $entry->openingStockEntryItems()->create([
        'product_variant_id' => $this->variant->id,
        'quantity' => 3,
        'unit_cost' => 10,
    ]);

    $response = $this->postJson("/api/admin/opening-stock-entry/{$entry->id}/approve");

    $response->assertUnprocessable();
});

test('opening stock movement posts GL dr inventory cr opening equity', function () {
    $inventory = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Inventory',
        'code' => 'INV-OSE',
    ]);
    $equity = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Opening Stock Equity',
        'code' => 'OSE-EQ',
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'inventory_account_id' => $inventory->id,
        'opening_stock_equity_account_id' => $equity->id,
    ]);

    $create = $this->postJson('/api/admin/opening-stock-entry', openingStockPayload($this, [
        'status' => 'approved',
    ]));

    $create->assertCreated();

    $movement = StockMovement::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('product_variant_id', $this->variant->id)
        ->first();

    expect($movement->gl_journal_id)->not->toBeNull();

    $journal = Journal::withoutGlobalScopes()->find($movement->gl_journal_id);
    expect($journal)->not->toBeNull();

    $drInventory = $journal->journalItems->where('account_id', $inventory->id)->sum('dr_amount');
    $crEquity = $journal->journalItems->where('account_id', $equity->id)->sum('cr_amount');

    expect((float) $drInventory)->toBe(125.0)
        ->and((float) $crEquity)->toBe(125.0);
});

test('product import opening stock uses inventory pipeline not direct stock upsert', function () {
    $job = DataTransferJob::create([
        'uuid' => (string) Str::uuid(),
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'user_id' => $this->user->id,
        'direction' => DataTransferDirectionEnum::Import,
        'entity_type' => DataTransferEntityTypeEnum::Product,
        'status' => DataTransferStatusEnum::Processing,
        'options' => ['duplicate_mode' => 'create_only'],
        'stats' => ['total_rows' => 0, 'processed' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'valid' => 0, 'invalid' => 0],
    ]);

    $lookups = ProductImportLookupCache::forCompany($this->company->id);
    $validator = new ProductImportRowValidator;
    $result = $validator->validate([
        'name' => 'Imported Widget',
        'code' => 'IMP-001',
        'product_type' => 'product',
        'category' => $this->category->name,
        'unit' => $this->unit->name,
        'sales_price' => '50',
        'purchase_price' => '20',
        'warehouse' => $this->warehouse->name,
        'quantity' => '7',
    ], $lookups);

    expect($result['errors'])->toBeEmpty();

    $import = app(ProductImportService::class);
    $outcome = $import->importRow($job, $result['normalized'], $lookups);

    expect($outcome['action'])->toBe('imported');

    $movement = StockMovement::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('product_variant_id', $outcome['variant_id'])
        ->first();

    expect($movement)->not->toBeNull()
        ->and($movement->type)->toBe(ChangeTypeEnum::OPENING_STOCK)
        ->and($movement->quantity)->toBe(7);

    $entry = OpeningStockEntry::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->first();

    expect($entry)->not->toBeNull()
        ->and($entry->status)->toBe(StatusEnum::APPROVED);
});

test('opening stock entry service approve is idempotent when already approved', function () {
    $entry = OpeningStockEntry::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'date' => '2026-01-15',
        'warehouse_id' => $this->warehouse->id,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
    ]);

    $entry->openingStockEntryItems()->create([
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
        'unit_cost' => 5,
    ]);

    app(OpeningStockEntryService::class)->approve($entry, $this->user);

    expect(StockMovement::withoutGlobalScopes()->count())->toBe(0);
});
